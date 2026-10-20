<?php

namespace App\Services;

use App\Enums\StatutAnnee;
use App\Enums\StatutPeriode;
use App\Models\AnneeScolaire;
use App\Models\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnneeScolaireService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Crée l'année et, si demandé, ses périodes (dates réparties également, modifiables ensuite).
     */
    public function creer(array $donnees, string $decoupage): AnneeScolaire
    {
        return DB::transaction(function () use ($donnees, $decoupage) {
            $annee = AnneeScolaire::create([
                'libelle' => $donnees['libelle'],
                'date_debut' => $donnees['date_debut'],
                'date_fin' => $donnees['date_fin'],
                'statut' => StatutAnnee::Preparation,
                'est_active' => false,
            ]);

            $nombre = ['trimestres' => 3, 'semestres' => 2][$decoupage] ?? 0;
            $debut = CarbonImmutable::parse($donnees['date_debut']);
            $fin = CarbonImmutable::parse($donnees['date_fin']);
            $joursParPeriode = intdiv((int) $debut->diffInDays($fin) + 1, max($nombre, 1));

            for ($i = 1; $i <= $nombre; $i++) {
                $debutPeriode = $debut->addDays(($i - 1) * $joursParPeriode);
                Periode::create([
                    'annee_scolaire_id' => $annee->id,
                    'libelle' => ($i === 1 ? '1er' : "{$i}e").' '.($nombre === 3 ? 'trimestre' : 'semestre'),
                    'ordre' => $i,
                    'date_debut' => $debutPeriode,
                    'date_fin' => $i === $nombre ? $fin : $debutPeriode->addDays($joursParPeriode - 1),
                    'statut' => StatutPeriode::Ouverte,
                ]);
            }

            $this->journal->enregistrer('annee.creee', $annee, "Année scolaire {$annee->libelle} créée");

            return $annee;
        });
    }

    /**
     * RG-ANN-01 : une seule année active à la fois.
     */
    public function activer(AnneeScolaire $annee): void
    {
        DB::transaction(function () use ($annee) {
            $precedente = AnneeScolaire::active();
            AnneeScolaire::query()->where('est_active', true)->update(['est_active' => false]);
            $annee->update(['est_active' => true, 'statut' => StatutAnnee::EnCours]);

            $this->journal->enregistrer('annee.activee', $annee, "Année {$annee->libelle} définie comme année active",
                ['annee_active' => $precedente?->libelle], ['annee_active' => $annee->libelle]);
        });
    }

    /**
     * RG-ANN-04 : une année clôturée passe en lecture seule ; toutes ses périodes sont clôturées.
     */
    public function cloturer(AnneeScolaire $annee): void
    {
        DB::transaction(function () use ($annee) {
            $annee->update(['statut' => StatutAnnee::Cloturee]);
            $annee->periodes()->update(['statut' => StatutPeriode::Cloturee]);
            $this->journal->enregistrer('annee.cloturee', $annee, "Année {$annee->libelle} clôturée");
        });
    }

    public function changerStatutPeriode(Periode $periode, StatutPeriode $statut): void
    {
        $avant = $periode->statut->value;
        $periode->update(['statut' => $statut]);
        $this->journal->enregistrer(
            $statut === StatutPeriode::Cloturee ? 'periode.cloturee' : 'periode.rouverte',
            $periode, "{$periode->libelle} ({$periode->anneeScolaire->libelle}) : {$statut->libelle()}",
            ['statut' => $avant], ['statut' => $statut->value],
        );
    }
}
