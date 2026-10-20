<?php

namespace App\Services;

use App\Enums\StatutEvaluation;
use App\Enums\StatutInscription;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\Periode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Évaluations et notes (RG-NOT-01 à 03, 09 à 11).
 */
class EvaluationService
{
    public function __construct(private JournalService $journal) {}

    /**
     * @param  array{periode_id: int, libelle: string, type: string, date_evaluation: string, bareme: float|string, poids: float|string}  $donnees
     */
    public function creer(Affectation $affectation, array $donnees, User $auteur): Evaluation
    {
        $periode = Periode::findOrFail($donnees['periode_id']);
        $this->verifierPeriode($affectation, $periode, $donnees['date_evaluation']);

        $evaluation = Evaluation::create(array_merge($donnees, [
            'affectation_id' => $affectation->id,
            'statut' => StatutEvaluation::Brouillon,
            'created_by' => $auteur->id,
        ]));
        $this->journal->enregistrer('evaluation.creee', $evaluation, "« {$evaluation->libelle} » créée ({$affectation->libelle()}, {$periode->libelle})");

        return $evaluation;
    }

    public function modifier(Evaluation $evaluation, array $donnees): void
    {
        $this->verifierModifiable($evaluation);
        $periode = Periode::findOrFail($donnees['periode_id']);
        $this->verifierPeriode($evaluation->affectation, $periode, $donnees['date_evaluation']);

        // Le barème ne peut pas descendre sous une note déjà saisie (RG-NOT-02).
        $max = (float) $evaluation->notes()->max('valeur');
        if ((float) $donnees['bareme'] < $max) {
            throw ValidationException::withMessages(['bareme' => 'Des notes dépassent ce barème (note maximale saisie : '.note_fr($max).').']);
        }

        $avant = $evaluation->only(['libelle', 'type', 'date_evaluation', 'bareme', 'poids', 'periode_id']);
        $evaluation->update($donnees);
        $this->journal->enregistrer('evaluation.modifiee', $evaluation, "« {$evaluation->libelle} » modifiée",
            $this->serialiser($avant), $this->serialiser($evaluation->only(array_keys($avant))));
    }

    public function supprimer(Evaluation $evaluation): void
    {
        $this->verifierModifiable($evaluation);
        if ($evaluation->notes()->exists()) {
            throw ValidationException::withMessages(['evaluation' => 'Des notes sont saisies : une évaluation notée ne peut pas être supprimée.']);
        }
        $evaluation->delete();
        $this->journal->enregistrer('evaluation.supprimee', $evaluation->affectation, "« {$evaluation->libelle} » supprimée");
    }

    /**
     * Élèves à noter : présents dans la classe à la date de l'évaluation, plus ceux qui ont déjà une note.
     *
     * @return Collection<int, Inscription>
     */
    public function eleves(Evaluation $evaluation): Collection
    {
        $date = $evaluation->date_evaluation->toDateString();

        return Inscription::query()
            ->with('eleve')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $c) => $c->where('classe_id', $evaluation->affectation->classe_id)
                    ->whereDate('inscriptions.date_inscription', '<=', $date)
                    ->where(fn (Builder $s) => $s->where('inscriptions.statut', StatutInscription::Inscrit)->orWhereDate('inscriptions.date_sortie', '>', $date)))
                ->orWhereHas('notes', fn (Builder $n) => $n->where('evaluation_id', $evaluation->id)))
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->orderBy('eleves.nom')->orderBy('eleves.prenoms')
            ->select('inscriptions.*')
            ->get();
    }

    /**
     * Enregistre la grille de notes. Une ligne vide (ni note ni absence) supprime la note existante.
     * Après un déverrouillage, chaque modification est journalisée avec l'ancienne valeur (RG-NOT-11).
     *
     * @param  array<int|string, array{valeur?: ?string, est_absent?: bool|string|null, observation?: ?string}>  $lignes  inscription_id => saisie
     * @return int nombre de notes saisies après l'enregistrement
     */
    public function saisirNotes(Evaluation $evaluation, array $lignes): int
    {
        $this->verifierModifiable($evaluation);
        $eleves = $this->eleves($evaluation)->keyBy('id');
        $bareme = (float) $evaluation->bareme;
        $erreurs = [];
        $propres = [];

        foreach ($lignes as $id => $ligne) {
            if (! $eleves->has((int) $id)) {
                throw ValidationException::withMessages(['notes' => 'Un des élèves saisis n\'appartient pas à cette classe.']);
            }
            $absent = filter_var($ligne['est_absent'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $brut = trim(str_replace([',', ' '], ['.', ''], (string) ($ligne['valeur'] ?? '')));
            $valeur = null;
            if (! $absent && $brut !== '') {
                if (! preg_match('/^\d+(\.\d{1,2})?$/', $brut) || (float) $brut > $bareme) {
                    $erreurs["notes.{$id}.valeur"] = "Note invalide pour {$eleves[(int) $id]->eleve->nom_complet} : nombre entre 0 et ".note_fr($bareme).', 2 décimales au plus.';

                    continue;
                }
                $valeur = (float) $brut;
            }
            $propres[(int) $id] = ['valeur' => $valeur, 'est_absent' => $absent, 'observation' => mb_substr(trim((string) ($ligne['observation'] ?? '')), 0, 255) ?: null];
        }
        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        DB::transaction(function () use ($evaluation, $propres, $eleves) {
            $existantes = $evaluation->notes()->get()->keyBy('inscription_id');
            $avant = [];
            $apres = [];

            foreach ($propres as $id => $saisie) {
                $note = $existantes->get($id);
                $ancienne = $note ? ($note->est_absent ? 'absent' : ($note->valeur === null ? null : (float) $note->valeur)) : null;
                $nouvelle = $saisie['est_absent'] ? 'absent' : $saisie['valeur'];

                if ($saisie['valeur'] === null && ! $saisie['est_absent']) {
                    $note?->delete();
                } else {
                    Note::updateOrCreate(['evaluation_id' => $evaluation->id, 'inscription_id' => $id], $saisie);
                }

                if ($ancienne !== $nouvelle) {
                    $nom = $eleves[$id]->eleve->nom_complet;
                    $avant[$nom] = $ancienne;
                    $apres[$nom] = $nouvelle;
                }
            }

            if ($evaluation->deverrouillee_at && $apres !== []) {
                $this->journal->enregistrer('notes.modifiees_apres_deverrouillage', $evaluation,
                    count($apres)." note(s) modifiée(s) : « {$evaluation->libelle} » ({$evaluation->affectation->libelle()})", $avant, $apres);
            }
        });

        return $evaluation->notes()->count();
    }

    /**
     * RG-NOT-09 : validation (verrouillage) quand chaque élève a une note ou est marqué absent.
     */
    public function valider(Evaluation $evaluation, User $user): void
    {
        $this->verifierModifiable($evaluation);
        $notes = $evaluation->notes()->pluck('inscription_id');
        $manquants = $this->eleves($evaluation)->reject(fn ($i) => $notes->contains($i->id) || ! $i->estActive());
        if ($manquants->isNotEmpty()) {
            throw ValidationException::withMessages(['evaluation' => $manquants->count().' élève(s) sans note ni absence : '
                .$manquants->take(5)->map(fn ($i) => $i->eleve->nom_complet)->join(', ').($manquants->count() > 5 ? '…' : '').'.']);
        }

        $evaluation->update(['statut' => StatutEvaluation::Validee, 'validee_par' => $user->id, 'validee_at' => now()]);
        $this->journal->enregistrer('evaluation.validee', $evaluation, "« {$evaluation->libelle} » validée ({$evaluation->affectation->libelle()})");
    }

    /**
     * Réservé à la direction, avec motif journalisé (RG-NOT-09).
     */
    public function deverrouiller(Evaluation $evaluation, string $motif): void
    {
        if (! $evaluation->estValidee()) {
            throw ValidationException::withMessages(['evaluation' => 'Cette évaluation n\'est pas validée.']);
        }
        $this->verifierPeriodeEtPublication($evaluation);

        $evaluation->update(['statut' => StatutEvaluation::Brouillon, 'deverrouillee_at' => now()]);
        $this->journal->enregistrer('evaluation.deverrouillee', $evaluation, "« {$evaluation->libelle} » déverrouillée ({$evaluation->affectation->libelle()})",
            ['statut' => 'validee'], ['statut' => 'brouillon', 'motif' => $motif]);
    }

    /**
     * Raison pour laquelle l'évaluation n'est pas modifiable, ou null si elle l'est.
     */
    public function blocage(Evaluation $evaluation): ?string
    {
        if ($evaluation->estValidee()) {
            return 'Évaluation validée : les notes sont verrouillées.';
        }
        try {
            $this->verifierPeriodeEtPublication($evaluation);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }

        return null;
    }

    private function verifierModifiable(Evaluation $evaluation): void
    {
        if ($raison = $this->blocage($evaluation)) {
            throw ValidationException::withMessages(['evaluation' => $raison]);
        }
    }

    private function verifierPeriodeEtPublication(Evaluation $evaluation): void
    {
        $periode = $evaluation->periode;
        if (! $periode->estOuverte()) {
            throw ValidationException::withMessages(['evaluation' => "La période « {$periode->libelle} » est clôturée (RG-ANN-03)."]);
        }
        if ($evaluation->affectation->classe->estPublieePour($periode)) {
            throw ValidationException::withMessages(['evaluation' => 'Les résultats de cette classe sont publiés pour la période : les notes sont figées.']);
        }
    }

    private function verifierPeriode(Affectation $affectation, Periode $periode, string $date): void
    {
        if ($periode->annee_scolaire_id !== $affectation->classe->annee_scolaire_id) {
            throw ValidationException::withMessages(['periode_id' => 'Cette période n\'appartient pas à l\'année de la classe.']);
        }
        if (! $periode->estOuverte()) {
            throw ValidationException::withMessages(['periode_id' => "La période « {$periode->libelle} » est clôturée."]);
        }
        $jour = CarbonImmutable::parse($date);
        if ($jour->lt($periode->date_debut) || $jour->gt($periode->date_fin)) {
            throw ValidationException::withMessages(['date_evaluation' => "La date doit être comprise dans la période ({$periode->date_debut->format('d/m/Y')} – {$periode->date_fin->format('d/m/Y')})."]);
        }
        if ($affectation->classe->estPublieePour($periode)) {
            throw ValidationException::withMessages(['periode_id' => 'Les résultats de cette classe sont déjà publiés pour cette période.']);
        }
    }

    private function serialiser(array $valeurs): array
    {
        return array_map(fn ($v) => match (true) {
            $v instanceof \BackedEnum => $v->value,
            $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
            default => $v,
        }, $valeurs);
    }
}
