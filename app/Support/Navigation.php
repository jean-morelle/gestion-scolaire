<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu latéral adapté aux rôles et permissions de l'utilisateur connecté.
 * Le menu ne fait que refléter les droits : chaque route est protégée côté serveur.
 */
final class Navigation
{
    /**
     * @return list<array{titre: ?string, liens: list<array{libelle: string, route: string, icone: string, actif: string}>}>
     */
    public static function pour(User $user): array
    {
        $peut = fn (string ...$permissions) => collect($permissions)->contains(fn ($p) => $user->can($p));
        $est = fn (Role $role) => $user->aRole($role);

        $sections = [
            [null, [
                ['Tableau de bord', 'tableau-de-bord', 'accueil', 'tableau-de-bord', true],
                ['Annonces', 'annonces.index', 'megaphone', 'annonces.*', true],
                ['Notifications', 'notifications.index', 'cloche', 'notifications.*', true],
            ]],
            ['Espace famille', [
                ['Mes enfants', 'famille.enfants', 'utilisateurs', 'famille.*', $est(Role::Parent)],
            ]],
            ['Mon espace élève', [
                ['Mon espace', 'eleve.accueil', 'accueil', 'eleve.accueil', $est(Role::Eleve)],
                ['Mes résultats', 'eleve.resultats', 'diplome', 'eleve.resultats*', $est(Role::Eleve)],
                ['Mon assiduité', 'eleve.assiduite', 'coche', 'eleve.assiduite', $est(Role::Eleve)],
                ['Emploi du temps', 'eleve.emploi-du-temps', 'calendrier', 'eleve.emploi-du-temps', $est(Role::Eleve)],
                ['Mes cours', 'eleve.cours', 'livre', 'eleve.cours*', $est(Role::Eleve)],
                ['Mes devoirs', 'eleve.devoirs', 'document', 'eleve.devoirs*', $est(Role::Eleve)],
            ]],
            ['Scolarité', [
                ['Élèves', 'eleves.index', 'utilisateurs', 'eleves.*', $peut('eleves.consulter')],
                ['Inscriptions', 'inscriptions.index', 'inscription', 'inscriptions.*', $peut('inscriptions.gerer')],
                ['Classes', 'classes.index', 'classe', 'classes.*', $peut('classes.gerer', 'eleves.consulter')],
                ['Emplois du temps', 'emplois-du-temps.index', 'calendrier', 'emplois-du-temps.*', $peut('emplois_du_temps.gerer', 'eleves.consulter', 'enseignants.gerer') || $est(Role::Enseignant)],
            ]],
            ['Pédagogie', [
                ['Mes classes', 'mes-classes.index', 'classe', 'mes-classes.*', $est(Role::Enseignant)],
                ['Enseignants', 'enseignants.index', 'enseignant', 'enseignants.*', $peut('enseignants.gerer')],
                ['Affectations', 'affectations.index', 'lien', 'affectations.*', $peut('affectations.gerer')],
                ['Évaluations et notes', 'evaluations.index', 'crayon', 'evaluations.*|resultats.affectation', $peut('notes.saisir', 'notes.consulter')],
                ['Résultats des classes', 'resultats.classe', 'graphique', 'resultats.classe', $peut('notes.consulter') || (bool) $user->enseignant?->classesPrincipales()->exists()],
                ['Appel', 'appels.index', 'coche', 'appels.*', $peut('presences.saisir', 'presences.saisir_ses_classes', 'presences.consulter')],
                ['Justificatifs', 'justificatifs.index', 'trombone', 'justificatifs.*', $peut('presences.justifier')],
                ['Bulletins', 'bulletins.index', 'diplome', 'bulletins.*', $peut('bulletins.consulter', 'bulletins.publier')],
                ['Cours et devoirs', 'cours.index', 'livre', 'cours.*|devoirs.*', $peut('cours.gerer', 'rapports.scolaires')],
                ['Statistiques scolaires', 'rapports.scolaires', 'graphique', 'rapports.scolaires*', $peut('rapports.scolaires')],
            ]],
            ['Finances', [
                ['Situation des élèves', 'finances.index', 'portefeuille', 'finances.*', $peut('finances.consulter')],
                ['Paiements', 'paiements.index', 'billet', 'paiements.*', $peut('finances.consulter')],
                ['Frais et échéances', 'grilles-frais.index', 'reglages', 'grilles-frais.*|types-frais.*', $peut('frais.configurer')],
                ['Dépenses', 'depenses.index', 'recu', 'depenses.*', $peut('depenses.gerer', 'depenses.annuler')],
                ['Rapports financiers', 'rapports.financiers', 'graphique', 'rapports.financiers*', $peut('rapports.financiers')],
            ]],
            ['Administration', [
                ['Établissement', 'etablissement.edit', 'batiment', 'etablissement.*', $peut('etablissement.gerer')],
                ['Années et périodes', 'annees.index', 'calendrier', 'annees.*|periodes.*', $peut('etablissement.gerer')],
                ['Niveaux et matières', 'referentiel.index', 'liste', 'referentiel.*|niveaux.*|matieres.*', $peut('etablissement.gerer')],
                ['Utilisateurs', 'utilisateurs.index', 'cle', 'utilisateurs.*', $peut('utilisateurs.gerer')],
                ['Journal d\'audit', 'journal.index', 'bouclier', 'journal.*', $peut('journal.consulter')],
            ]],
        ];

        $menu = [];
        foreach ($sections as [$titre, $liens]) {
            $visibles = [];
            foreach ($liens as [$libelle, $route, $icone, $actif, $autorise]) {
                if ($autorise && Route::has($route)) {
                    $visibles[] = ['libelle' => $libelle, 'route' => $route, 'icone' => $icone, 'actif' => $actif];
                }
            }
            if ($visibles !== []) {
                $menu[] = ['titre' => $titre, 'liens' => $visibles];
            }
        }

        return $menu;
    }
}
