<?php

namespace App\Services;

use App\Models\Appreciation;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Periode;
use App\Models\Publication;
use App\Models\User;
use App\Notifications\BulletinPublie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publication des résultats d'une classe pour une période (RG-NOT-10) : chaque bulletin est figé
 * (instantané) au moment de la publication. Dépublier rouvre les corrections ; republier crée une
 * nouvelle version, historisée dans le journal.
 */
class BulletinService
{
    public function __construct(
        private MoyenneService $moyennes,
        private AppelService $appels,
        private JournalService $journal,
    ) {}

    public function publication(Classe $classe, Periode $periode): ?Publication
    {
        return Publication::query()->where('classe_id', $classe->id)->where('periode_id', $periode->id)->first();
    }

    public function estPubliee(Classe $classe, Periode $periode): bool
    {
        $publication = $this->publication($classe, $periode);

        return $publication !== null && $publication->depubliee_at === null;
    }

    /**
     * Appréciations générales et décisions du conseil, modifiables tant que la classe n'est pas publiée.
     *
     * @param  array<int|string, array{appreciation_generale?: ?string, mention?: ?string}>  $lignes  inscription_id => saisie
     */
    public function enregistrerAppreciations(Classe $classe, Periode $periode, array $lignes, User $auteur): int
    {
        $this->verifierNonPubliee($classe, $periode);
        $attendues = $this->moyennes->inscriptionsDeLaPeriode($classe, $periode)->keyBy('id');

        return DB::transaction(function () use ($lignes, $attendues, $periode, $auteur) {
            $n = 0;
            foreach ($lignes as $id => $ligne) {
                if (! $attendues->has((int) $id)) {
                    continue;
                }
                $texte = trim((string) ($ligne['appreciation_generale'] ?? '')) ?: null;
                $mention = trim((string) ($ligne['mention'] ?? '')) ?: null;
                $existante = Appreciation::query()->where('inscription_id', $id)->where('periode_id', $periode->id)->first();
                if (! $texte && ! $mention) {
                    $existante?->delete();

                    continue;
                }
                Appreciation::updateOrCreate(
                    ['inscription_id' => $id, 'periode_id' => $periode->id],
                    ['appreciation_generale' => $texte, 'mention' => $mention, 'saisie_par' => $auteur->id],
                );
                $n++;
            }

            return $n;
        });
    }

    /**
     * @return int nombre de bulletins publiés
     */
    public function publier(Classe $classe, Periode $periode, User $auteur, bool $forcer = false): int
    {
        if ($periode->annee_scolaire_id !== $classe->annee_scolaire_id) {
            throw ValidationException::withMessages(['periode' => 'Cette période n\'appartient pas à l\'année de la classe.']);
        }
        $this->verifierNonPubliee($classe, $periode);

        // Seules les évaluations validées comptent : un brouillon n'est jamais publié.
        $resultats = $this->moyennes->resultatsClasse($classe, $periode, seulementValidees: true);
        if ($resultats['evaluations_non_validees'] > 0 && ! $forcer) {
            throw ValidationException::withMessages(['publication' => "{$resultats['evaluations_non_validees']} évaluation(s) ne sont pas validées. "
                .'Faites-les valider, ou publiez en forçant : elles ne seront pas comptées.']);
        }
        if ($resultats['classes'] === 0) {
            throw ValidationException::withMessages(['publication' => 'Aucune note validée pour cette classe et cette période : rien à publier.']);
        }

        $publies = DB::transaction(function () use ($classe, $periode, $auteur, $forcer, $resultats) {
            $publication = Publication::updateOrCreate(
                ['classe_id' => $classe->id, 'periode_id' => $periode->id],
                ['publiee_par' => $auteur->id, 'publiee_at' => now(), 'depubliee_at' => null],
            );
            $appreciations = Appreciation::query()->where('periode_id', $periode->id)
                ->whereIn('inscription_id', $resultats['eleves']->keys())->get()->keyBy('inscription_id');
            $annuel = $this->annuel($classe, $periode, $resultats['eleves']);
            $matieresHorsClasse = Matiere::query()->whereIn('id', $resultats['eleves']->flatMap(fn ($e) => array_keys($e['matieres']))
                ->diff($resultats['matieres']->keys())->unique())->get()->keyBy('id');

            $changements = ['avant' => [], 'apres' => []];
            $bulletins = collect();
            foreach ($resultats['eleves'] as $id => $eleve) {
                $existant = Bulletin::query()->where('inscription_id', $id)->where('periode_id', $periode->id)->first();
                $appreciation = $appreciations->get($id);
                $donnees = [
                    'publication_id' => $publication->id,
                    'moyenne_generale' => $eleve['moyenne'],
                    'rang' => $eleve['rang'],
                    'effectif' => $resultats['effectif'],
                    'mention' => $eleve['mention'],
                    'distinction' => $appreciation?->mention,
                    'appreciation' => $appreciation?->appreciation_generale,
                    'moyenne_annuelle' => $annuel[$id]['moyenne'] ?? null,
                    'rang_annuel' => $annuel[$id]['rang'] ?? null,
                    'lignes' => $this->lignes($eleve, $resultats['matieres'], $matieresHorsClasse),
                    'assiduite' => $this->appels->totaux($eleve['inscription'], $periode),
                    'version' => ($existant?->version ?? 0) + 1,
                    'publie_par' => $auteur->id,
                ];

                if ($existant && (round((float) $existant->moyenne_generale, 3) !== round((float) $eleve['moyenne'], 3) || $existant->rang !== $eleve['rang'])) {
                    $nom = $eleve['inscription']->eleve->nom_complet;
                    $changements['avant'][$nom] = ['moyenne' => $existant->moyenne_generale, 'rang' => $existant->rang];
                    $changements['apres'][$nom] = ['moyenne' => $eleve['moyenne'] === null ? null : round($eleve['moyenne'], 3), 'rang' => $eleve['rang']];
                }

                $bulletins->push($existant ? tap($existant)->update($donnees)
                    : Bulletin::create(['inscription_id' => $id, 'periode_id' => $periode->id] + $donnees));
            }

            $version = $bulletins->max('version');
            $this->journal->enregistrer($version > 1 ? 'bulletins.republies' : 'bulletins.publies', $publication,
                "Bulletins de {$classe->nom} — {$periode->libelle} ".($version > 1 ? "republiés (version {$version})" : 'publiés')
                    .($forcer && $resultats['evaluations_non_validees'] ? " en forçant ({$resultats['evaluations_non_validees']} évaluation(s) non validée(s) exclue(s))" : ''),
                $changements['avant'] ?: null, $changements['apres'] ?: null);

            return $bulletins;
        });

        $this->notifier($publies);

        return $publies->count();
    }

    /**
     * Retire la publication pour permettre une correction (motif obligatoire, journalisé). Les bulletins figés
     * sont conservés mais ne sont plus visibles des familles jusqu'à la republication.
     */
    public function depublier(Classe $classe, Periode $periode, string $motif): void
    {
        $publication = $this->publication($classe, $periode);
        if (! $publication || $publication->depubliee_at) {
            throw ValidationException::withMessages(['publication' => 'Les résultats de cette classe ne sont pas publiés pour cette période.']);
        }
        if (! $periode->estOuverte()) {
            throw ValidationException::withMessages(['publication' => "La période « {$periode->libelle} » est clôturée : rouvrez-la d'abord dans l'administration."]);
        }

        $publication->update(['depubliee_at' => now()]);
        $this->journal->enregistrer('bulletins.depublies', $publication, "Bulletins de {$classe->nom} — {$periode->libelle} retirés pour correction",
            null, ['motif' => $motif]);
    }

    /**
     * RG-NOT-08 : sur la dernière période, moyenne annuelle = moyenne des moyennes générales des périodes
     * (bulletins déjà publiés + période courante), non pondérée ; rang annuel sur cette moyenne.
     *
     * @param  Collection<int, array<string, mixed>>  $eleves
     * @return array<int, array{moyenne: ?float, rang: ?int}>
     */
    private function annuel(Classe $classe, Periode $periode, Collection $eleves): array
    {
        $periodes = $classe->anneeScolaire->periodes;
        if ($periodes->isEmpty() || $periode->ordre < $periodes->max('ordre')) {
            return [];
        }

        $precedents = Bulletin::query()->whereIn('inscription_id', $eleves->keys())
            ->whereIn('periode_id', $periodes->where('id', '!=', $periode->id)->pluck('id'))
            ->get()->groupBy('inscription_id');

        $moyennes = [];
        foreach ($eleves as $id => $eleve) {
            $valeurs = collect($precedents->get($id, []))->pluck('moyenne_generale')->push($eleve['moyenne'])->filter(fn ($m) => $m !== null);
            $moyennes[$id] = $valeurs->isEmpty() ? null : $valeurs->avg();
        }
        $rangs = $this->moyennes->rangs($moyennes);

        return collect($moyennes)->map(fn ($m, $id) => ['moyenne' => $m, 'rang' => $rangs[$id]])->all();
    }

    /**
     * Lignes du bulletin dans l'ordre défini par les affectations.
     *
     * @return list<array<string, mixed>>
     */
    private function lignes(array $eleve, Collection $matieres, Collection $matieresHorsClasse): array
    {
        $lignes = [];
        foreach ($matieres as $matiereId => $m) {
            $moyenne = $eleve['matieres'][$matiereId]['moyenne'] ?? null;
            $coefficient = (float) $m['affectation']->coefficient;
            $lignes[] = [
                'matiere' => $m['affectation']->matiere->libelle,
                'enseignant' => $m['affectation']->enseignant?->user->name,
                'coefficient' => $coefficient,
                'moyenne' => $moyenne,
                'points' => $moyenne === null ? null : $moyenne * $coefficient,
                'rang' => $eleve['matieres'][$matiereId]['rang'] ?? null,
                'moyenne_classe' => $m['moyenne'],
                'min' => $m['min'],
                'max' => $m['max'],
            ];
        }
        foreach ($eleve['matieres'] as $matiereId => $ligne) {
            if (! $matieres->has($matiereId) && $ligne['moyenne'] !== null) {
                $lignes[] = [
                    'matiere' => ($matieresHorsClasse[$matiereId]->libelle ?? 'Matière').' (ancienne classe)',
                    'enseignant' => null, 'coefficient' => $ligne['coefficient'], 'moyenne' => $ligne['moyenne'],
                    'points' => $ligne['moyenne'] * $ligne['coefficient'], 'rang' => null, 'moyenne_classe' => null, 'min' => null, 'max' => null,
                ];
            }
        }

        return $lignes;
    }

    /**
     * @param  Collection<int, Bulletin>  $bulletins
     */
    private function notifier(Collection $bulletins): void
    {
        foreach ($bulletins as $bulletin) {
            $bulletin->load(['periode', 'inscription.eleve.responsables.user', 'inscription.eleve.user']);
            $destinataires = $bulletin->inscription->eleve->responsables->pluck('user')
                ->push($bulletin->inscription->eleve->user)->filter()->unique('id');
            foreach ($destinataires as $user) {
                $user->notify(new BulletinPublie($bulletin));
            }
        }
    }

    private function verifierNonPubliee(Classe $classe, Periode $periode): void
    {
        $publication = $this->publication($classe, $periode);
        if ($publication && ! $publication->depubliee_at) {
            throw ValidationException::withMessages(['publication' => 'Les bulletins de cette classe sont déjà publiés pour cette période : retirez la publication pour corriger.']);
        }
    }
}
