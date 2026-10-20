<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Models\AnneeScolaire;
use App\Models\FraisEleve;
use App\Models\GrilleFrais;
use App\Models\Inscription;
use App\Models\Tranche;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grilles de frais, échéanciers (RG-FIN-02) et attribution des frais aux élèves (RG-FIN-03).
 * Les frais d'un élève sont une copie de la grille au moment de l'attribution : modifier la grille ensuite
 * ne change pas ce qui a déjà été facturé.
 */
class FraisService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Frais obligatoires de l'année et du niveau, pour chaque type que l'élève n'a pas encore.
     * Une grille propre à la catégorie de l'élève remplace la grille « toutes catégories » du même type.
     *
     * @return int nombre de lignes créées
     */
    public function genererObligatoires(Inscription $inscription): int
    {
        $categorie = (string) $inscription->categorie_frais;
        $grilles = $this->grillesApplicables($inscription)
            ->filter(fn (GrilleFrais $g) => $g->typeFrais->est_obligatoire && in_array($g->categorie, ['', $categorie], true));
        $grilles = $grilles->reject(fn (GrilleFrais $g) => $g->categorie === ''
            && $grilles->contains(fn (GrilleFrais $autre) => $autre->categorie !== '' && $autre->type_frais_id === $g->type_frais_id));

        $dejaFactures = $inscription->fraisEleves()->pluck('type_frais_id')->unique()->all();
        $lignes = 0;
        foreach ($grilles as $grille) {
            if (! in_array($grille->type_frais_id, $dejaFactures, true)) {
                $lignes += $this->copierGrille($inscription, $grille);
            }
        }

        return $lignes;
    }

    /**
     * Frais facultatif (cantine, transport, tenue…) attribué individuellement.
     */
    public function attribuer(Inscription $inscription, GrilleFrais $grille, User $user): int
    {
        if ($grille->annee_scolaire_id !== $inscription->annee_scolaire_id || $grille->niveau_id !== $inscription->classe->niveau_id) {
            throw ValidationException::withMessages(['grille_frais_id' => 'Ce frais ne correspond pas à l\'année ou au niveau de l\'élève.']);
        }
        if ($inscription->fraisEleves()->where('type_frais_id', $grille->type_frais_id)->exists()) {
            throw ValidationException::withMessages(['grille_frais_id' => "« {$grille->typeFrais->libelle} » est déjà facturé à cet élève."]);
        }

        return DB::transaction(function () use ($inscription, $grille) {
            $lignes = $this->copierGrille($inscription, $grille);
            $this->journal->enregistrer('frais.attribue', $inscription, "« {$grille->typeFrais->libelle} » (".montant($grille->montant).") attribué à {$inscription->eleve->nom_complet}");

            return $lignes;
        });
    }

    /**
     * Retire un type de frais attribué par erreur : seulement s'il n'a reçu ni paiement ni remise.
     */
    public function retirer(Inscription $inscription, int $typeFraisId): void
    {
        $lignes = $inscription->fraisEleves()->where('type_frais_id', $typeFraisId)->withCount(['imputations', 'remises'])->get();
        if ($lignes->isEmpty()) {
            throw ValidationException::withMessages(['frais' => 'Ce frais n\'est pas facturé à cet élève.']);
        }
        if ($lignes->sum('imputations_count') + $lignes->sum('remises_count') > 0) {
            throw ValidationException::withMessages(['frais' => 'Ce frais a déjà reçu un paiement ou une remise (même annulés) : il ne peut pas être retiré.']);
        }

        DB::transaction(function () use ($inscription, $lignes) {
            $libelle = $lignes->first()->typeFrais->libelle;
            $total = (int) $lignes->sum('montant_du');
            FraisEleve::query()->whereIn('id', $lignes->pluck('id'))->delete();
            $this->journal->enregistrer('frais.retire', $inscription, "« {$libelle} » (".montant($total).") retiré pour {$inscription->eleve->nom_complet}");
        });
    }

    /**
     * Applique les frais obligatoires aux élèves inscrits de l'année (nouveaux types ou grilles créées après coup).
     */
    public function genererPourLAnnee(AnneeScolaire $annee): int
    {
        if ($annee->estCloturee()) {
            throw ValidationException::withMessages(['annee' => 'Cette année est clôturée.']);
        }

        return DB::transaction(function () use ($annee) {
            $lignes = 0;
            Inscription::query()->with(['classe', 'eleve'])->where('annee_scolaire_id', $annee->id)
                ->where('statut', StatutInscription::Inscrit)
                ->each(function (Inscription $inscription) use (&$lignes) {
                    $lignes += $this->genererObligatoires($inscription);
                });
            $this->journal->enregistrer('frais.generes', $annee, "{$lignes} ligne(s) de frais générée(s) pour les élèves inscrits en {$annee->libelle}");

            return $lignes;
        });
    }

    /**
     * Crée ou modifie une grille et son échéancier. Σ tranches = montant (RG-FIN-02).
     *
     * @param  array{annee_scolaire_id: int, niveau_id: int, type_frais_id: int, categorie: ?string, montant: int}  $donnees
     * @param  list<array{libelle: string, montant: int, date_limite: string}>  $tranches
     */
    public function enregistrerGrille(array $donnees, array $tranches, ?GrilleFrais $grille = null): GrilleFrais
    {
        $donnees['categorie'] = trim((string) ($donnees['categorie'] ?? ''));
        $tranches = array_values(array_filter($tranches, fn ($t) => filled($t['libelle'] ?? null) || filled($t['montant'] ?? null)));

        if ($tranches !== [] && array_sum(array_map(fn ($t) => (int) $t['montant'], $tranches)) !== (int) $donnees['montant']) {
            throw ValidationException::withMessages(['tranches' => 'La somme des tranches ('.montant(array_sum(array_map(fn ($t) => (int) $t['montant'], $tranches)))
                .') doit être égale au montant total ('.montant((int) $donnees['montant']).').']);
        }
        $doublon = GrilleFrais::query()->where('annee_scolaire_id', $donnees['annee_scolaire_id'])->where('niveau_id', $donnees['niveau_id'])
            ->where('type_frais_id', $donnees['type_frais_id'])->where('categorie', $donnees['categorie'])
            ->when($grille, fn ($q) => $q->whereKeyNot($grille->id))->exists();
        if ($doublon) {
            throw ValidationException::withMessages(['type_frais_id' => 'Une grille existe déjà pour ce type de frais, ce niveau et cette catégorie.']);
        }
        if (AnneeScolaire::findOrFail($donnees['annee_scolaire_id'])->estCloturee()) {
            throw ValidationException::withMessages(['annee_scolaire_id' => 'Cette année est clôturée.']);
        }

        return DB::transaction(function () use ($donnees, $tranches, $grille) {
            $avant = $grille ? ['montant' => $grille->montant, 'tranches' => $grille->tranches->map->only(['libelle', 'montant'])->all()] : null;
            $grille ??= new GrilleFrais;
            $grille->fill($donnees)->save();

            // L'échéancier est remplacé ; les frais déjà facturés gardent leur copie (tranche_id passe à null).
            $grille->tranches()->delete();
            foreach ($tranches as $i => $t) {
                Tranche::create(['grille_frais_id' => $grille->id, 'libelle' => $t['libelle'], 'montant' => (int) $t['montant'],
                    'date_limite' => $t['date_limite'], 'ordre' => $i + 1]);
            }
            $grille->load(['tranches', 'typeFrais', 'niveau']);

            $this->journal->enregistrer($avant ? 'grille_frais.modifiee' : 'grille_frais.creee', $grille,
                "{$grille->typeFrais->libelle} — {$grille->niveau->libelle} : ".montant($grille->montant), $avant,
                ['montant' => $grille->montant, 'tranches' => $grille->tranches->map->only(['libelle', 'montant'])->all()]);

            return $grille;
        });
    }

    public function supprimerGrille(GrilleFrais $grille): void
    {
        DB::transaction(function () use ($grille) {
            $grille->load(['typeFrais', 'niveau']);
            $libelle = "{$grille->typeFrais->libelle} — {$grille->niveau->libelle}";
            $grille->tranches()->delete();
            $grille->delete();
            $this->journal->enregistrer('grille_frais.supprimee', null, "Grille {$libelle} supprimée (les frais déjà facturés sont conservés)");
        });
    }

    /**
     * Recopie les grilles d'une année vers une autre, en décalant les dates limites du nombre d'années d'écart.
     */
    public function copierGrilles(AnneeScolaire $source, AnneeScolaire $cible): int
    {
        $ecart = (int) round($source->date_debut->diffInYears($cible->date_debut));

        return DB::transaction(function () use ($source, $cible, $ecart) {
            $n = 0;
            foreach (GrilleFrais::query()->with('tranches')->where('annee_scolaire_id', $source->id)->get() as $modele) {
                $existe = GrilleFrais::query()->where(['annee_scolaire_id' => $cible->id, 'niveau_id' => $modele->niveau_id,
                    'type_frais_id' => $modele->type_frais_id, 'categorie' => $modele->categorie])->exists();
                if ($existe) {
                    continue;
                }
                $grille = GrilleFrais::create(['annee_scolaire_id' => $cible->id] + $modele->only(['niveau_id', 'type_frais_id', 'categorie', 'montant']));
                foreach ($modele->tranches as $t) {
                    Tranche::create(['grille_frais_id' => $grille->id, 'libelle' => $t->libelle, 'montant' => $t->montant,
                        'date_limite' => CarbonImmutable::parse($t->date_limite)->addYears($ecart), 'ordre' => $t->ordre]);
                }
                $n++;
            }
            $this->journal->enregistrer('grille_frais.copiees', $cible, "{$n} grille(s) recopiée(s) de {$source->libelle} vers {$cible->libelle}");

            return $n;
        });
    }

    /**
     * @return Collection<int, GrilleFrais>
     */
    public function grillesApplicables(Inscription $inscription): Collection
    {
        return GrilleFrais::query()->with(['tranches', 'typeFrais'])
            ->where('annee_scolaire_id', $inscription->annee_scolaire_id)
            ->where('niveau_id', $inscription->classe->niveau_id)
            ->orderBy('id')->get();
    }

    private function copierGrille(Inscription $inscription, GrilleFrais $grille): int
    {
        if ($grille->tranches->isEmpty()) {
            FraisEleve::create([
                'inscription_id' => $inscription->id, 'type_frais_id' => $grille->type_frais_id,
                'libelle' => $grille->typeFrais->libelle, 'montant_du' => $grille->montant, 'date_limite' => $inscription->date_inscription,
            ]);

            return 1;
        }
        foreach ($grille->tranches as $tranche) {
            FraisEleve::create([
                'inscription_id' => $inscription->id, 'type_frais_id' => $grille->type_frais_id, 'tranche_id' => $tranche->id,
                'libelle' => "{$grille->typeFrais->libelle} — {$tranche->libelle}", 'montant_du' => $tranche->montant, 'date_limite' => $tranche->date_limite,
            ]);
        }

        return $grille->tranches->count();
    }
}
