<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Catalogue des permissions et matrice rôle → permissions.
 * Source unique utilisée par le seeder et par la documentation (docs/roles-permissions.md).
 *
 * Les parents et les élèves n'ont aucune permission : leurs accès sont accordés uniquement
 * par les Policies, à partir du lien responsable–élève ou du compte élève.
 */
final class Autorisations
{
    public const PERMISSIONS = [
        'etablissement.gerer' => 'Paramétrer l\'établissement, les années, périodes, niveaux et matières',
        'utilisateurs.gerer' => 'Gérer les comptes du personnel et leurs rôles',
        'journal.consulter' => 'Consulter le journal d\'audit',
        'classes.gerer' => 'Créer et modifier les classes',
        'eleves.consulter' => 'Consulter les dossiers des élèves',
        'eleves.gerer' => 'Créer et modifier les élèves, responsables et pièces du dossier',
        'inscriptions.gerer' => 'Inscrire, réinscrire, changer de classe, enregistrer un départ',
        'enseignants.gerer' => 'Gérer les fiches enseignants',
        'affectations.gerer' => 'Affecter les enseignants et fixer les coefficients',
        'emplois_du_temps.gerer' => 'Construire les emplois du temps',
        'presences.saisir' => 'Faire l\'appel de toutes les classes',
        'presences.saisir_ses_classes' => 'Faire l\'appel des classes affectées',
        'presences.justifier' => 'Justifier les absences',
        'presences.consulter' => 'Consulter les présences de toutes les classes',
        'notes.saisir' => 'Créer des évaluations et saisir les notes de ses affectations',
        'notes.consulter' => 'Consulter toutes les notes',
        'evaluations.deverrouiller' => 'Déverrouiller une évaluation validée',
        'bulletins.consulter' => 'Consulter et imprimer les bulletins',
        'bulletins.publier' => 'Publier, déverrouiller et republier les bulletins',
        'cours.gerer' => 'Publier des cours, ressources et devoirs pour ses affectations',
        'finances.consulter' => 'Consulter les frais, paiements et soldes',
        'frais.configurer' => 'Configurer les frais, grilles et échéances',
        'paiements.enregistrer' => 'Encaisser les paiements et éditer les reçus',
        'paiements.annuler' => 'Annuler un paiement',
        'remises.accorder' => 'Accorder ou annuler une remise',
        'depenses.gerer' => 'Enregistrer les dépenses',
        'depenses.annuler' => 'Annuler une dépense',
        'rapports.financiers' => 'Consulter et exporter les rapports financiers',
        'rapports.scolaires' => 'Consulter et exporter les statistiques scolaires',
        'annonces.gerer' => 'Publier des annonces',
    ];

    /**
     * @return array<string, list<string>> nom du rôle => permissions
     */
    public static function matrice(): array
    {
        return [
            // Pas d'accès aux notes ni aux opérations financières sans règle explicite.
            Role::Administrateur->value => [
                'etablissement.gerer', 'utilisateurs.gerer', 'journal.consulter', 'classes.gerer', 'annonces.gerer',
            ],
            Role::Directeur->value => [
                'journal.consulter', 'classes.gerer', 'eleves.consulter', 'enseignants.gerer', 'affectations.gerer',
                'emplois_du_temps.gerer', 'presences.consulter', 'presences.justifier', 'notes.consulter',
                'evaluations.deverrouiller', 'bulletins.consulter', 'bulletins.publier', 'finances.consulter',
                'paiements.annuler', 'remises.accorder', 'depenses.annuler', 'rapports.financiers',
                'rapports.scolaires', 'annonces.gerer',
            ],
            Role::Secretaire->value => [
                'eleves.consulter', 'eleves.gerer', 'inscriptions.gerer', 'bulletins.consulter', 'presences.consulter',
            ],
            Role::Censeur->value => [
                'eleves.consulter', 'emplois_du_temps.gerer', 'presences.saisir', 'presences.justifier',
                'presences.consulter', 'notes.consulter', 'bulletins.consulter', 'rapports.scolaires',
                'annonces.gerer',
            ],
            Role::Enseignant->value => [
                'presences.saisir_ses_classes', 'notes.saisir', 'cours.gerer',
            ],
            Role::Comptable->value => [
                'eleves.consulter', 'finances.consulter', 'frais.configurer', 'paiements.enregistrer',
                'depenses.gerer', 'rapports.financiers',
            ],
            Role::Parent->value => [],
            Role::Eleve->value => [],
        ];
    }
}
