<?php

namespace Database\Seeders;

use App\Enums\Cycle;
use App\Models\CategorieDepense;
use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\TypeFrais;
use Illuminate\Database\Seeder;

/**
 * Données de référence nécessaires à toute installation. Idempotent : peut être relancé
 * sans créer de doublons ni écraser les paramètres saisis par l'établissement.
 */
class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        if (! Etablissement::query()->exists()) {
            Etablissement::create([
                'nom' => 'Mon établissement',
                'devise' => 'FCFA',
                'parametres' => [
                    'note_sur' => 20,
                    'decimales' => 2,
                    'absence_evaluation' => 'ignoree',
                    'delai_modification_appel_heures' => 48,
                    'categorie_frais_obligatoire' => false,
                    'notifier_absences' => true,
                    'remise_devoirs_en_ligne' => true,
                    'taille_max_fichier_ko' => 5120,
                    'jours_rappel_echeance' => 7,
                    'mentions' => [
                        ['min' => 16, 'libelle' => 'Très bien'],
                        ['min' => 14, 'libelle' => 'Bien'],
                        ['min' => 12, 'libelle' => 'Assez bien'],
                        ['min' => 10, 'libelle' => 'Passable'],
                        ['min' => 0, 'libelle' => 'Insuffisant'],
                    ],
                ],
            ]);
        }

        // Les niveaux inutilisés par l'établissement peuvent être supprimés depuis l'interface.
        $niveaux = [
            ['CP1', Cycle::Primaire], ['CP2', Cycle::Primaire], ['CE1', Cycle::Primaire],
            ['CE2', Cycle::Primaire], ['CM1', Cycle::Primaire], ['CM2', Cycle::Primaire],
            ['6e', Cycle::College], ['5e', Cycle::College], ['4e', Cycle::College], ['3e', Cycle::College],
            ['2nde', Cycle::Lycee], ['1ère', Cycle::Lycee], ['Tle', Cycle::Lycee],
        ];
        foreach ($niveaux as $ordre => [$libelle, $cycle]) {
            Niveau::firstOrCreate(['libelle' => $libelle], ['cycle' => $cycle, 'ordre' => $ordre + 1]);
        }

        $matieres = [
            'FR' => 'Français', 'ANG' => 'Anglais', 'MATH' => 'Mathématiques',
            'PC' => 'Physique-Chimie', 'SVT' => 'Sciences de la Vie et de la Terre',
            'HG' => 'Histoire-Géographie', 'ECM' => 'Éducation civique et morale',
            'ESP' => 'Espagnol', 'ALL' => 'Allemand', 'PHILO' => 'Philosophie',
            'INFO' => 'Informatique', 'EPS' => 'Éducation physique et sportive',
        ];
        foreach ($matieres as $code => $libelle) {
            Matiere::firstOrCreate(['code' => $code], ['libelle' => $libelle]);
        }

        $typesFrais = [
            'INSCRIPTION' => 'Frais d\'inscription',
            'SCOLARITE' => 'Frais de scolarité',
        ];
        foreach ($typesFrais as $code => $libelle) {
            TypeFrais::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'est_obligatoire' => true]);
        }

        foreach (['Salaires et indemnités', 'Fournitures et consommables', 'Entretien et réparations', 'Eau, électricité, internet', 'Activités pédagogiques', 'Divers'] as $categorie) {
            CategorieDepense::firstOrCreate(['libelle' => $categorie]);
        }
    }
}
