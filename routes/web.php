<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\MotDePasseController;
use App\Http\Controllers\Famille;
use App\Http\Controllers\Finance;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\Pedagogie;
use App\Http\Controllers\Scolarite;
use App\Http\Controllers\VieScolaire;
use App\Http\Controllers\TableauDeBordController;
use Illuminate\Support\Facades\Route;

/*
| Toutes les routes internes exigent une session authentifiée (middleware auth) ; le middleware
| CompteActif (groupe web) écarte les comptes désactivés. Les permissions sont vérifiées par groupe
| (middleware permission) puis, pour chaque ressource, par les Policies.
*/

// Redirection par nom de route : reste correcte quand l'application est servie depuis un sous-dossier (XAMPP).
Route::get('/', fn () => redirect()->route('tableau-de-bord'));
Route::get('/etablissement/logo', [Admin\EtablissementController::class, 'logo'])->name('etablissement.logo');
Route::get('/etablissement/image-connexion', [Admin\EtablissementController::class, 'imageConnexion'])->name('etablissement.image-connexion');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');
    Route::get('/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mot-de-passe', [MotDePasseController::class, 'update'])->name('mot-de-passe.update');

    Route::get('/tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');
    Route::get('/recherche', RechercheController::class)->name('recherche');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'ouvrir'])->name('notifications.ouvrir');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.tout-lire');

    // ---------------------------------------------------------------- Annonces (lecture : tous ; gestion : annonces.gerer)
    Route::get('/annonces', [AnnonceController::class, 'index'])->name('annonces.index');
    Route::middleware('permission:annonces.gerer')->group(function () {
        Route::get('/annonces/create', [AnnonceController::class, 'create'])->name('annonces.create');
        Route::post('/annonces', [AnnonceController::class, 'store'])->name('annonces.store');
        Route::get('/annonces/{annonce}/edit', [AnnonceController::class, 'edit'])->name('annonces.edit');
        Route::put('/annonces/{annonce}', [AnnonceController::class, 'update'])->name('annonces.update');
        Route::delete('/annonces/{annonce}', [AnnonceController::class, 'destroy'])->name('annonces.destroy');
    });
    Route::get('/annonces/{annonce}', [AnnonceController::class, 'show'])->name('annonces.show');

    // ---------------------------------------------------------------- Espaces famille et élève
    Route::middleware('role:parent')->group(function () {
        Route::get('/famille', [Famille\SuiviController::class, 'enfants'])->name('famille.enfants');
        Route::get('/famille/enfants/{eleve}/{section?}', [Famille\SuiviController::class, 'enfant'])->name('famille.enfant');
    });
    Route::middleware('role:eleve')->group(function () {
        Route::get('/mon-espace', [Famille\SuiviController::class, 'monEspace'])->name('eleve.accueil');
        Route::get('/mon-espace/resultats', [Famille\SuiviController::class, 'monEspace'])->defaults('section', 'resultats')->name('eleve.resultats');
        Route::get('/mon-espace/assiduite', [Famille\SuiviController::class, 'monEspace'])->defaults('section', 'assiduite')->name('eleve.assiduite');
        Route::get('/mon-espace/emploi-du-temps', [Famille\SuiviController::class, 'monEspace'])->defaults('section', 'emploi-du-temps')->name('eleve.emploi-du-temps');
        Route::get('/mon-espace/cours', [Famille\ApprentissageController::class, 'cours'])->name('eleve.cours');
        Route::get('/mon-espace/cours/{cours}', [Famille\ApprentissageController::class, 'coursShow'])->name('eleve.cours.show');
        Route::get('/mon-espace/devoirs', [Famille\ApprentissageController::class, 'devoirs'])->name('eleve.devoirs');
        Route::get('/mon-espace/devoirs/{devoir}', [Famille\ApprentissageController::class, 'devoirShow'])->name('eleve.devoirs.show');
        Route::post('/mon-espace/devoirs/{devoir}/remise', [Famille\ApprentissageController::class, 'remettre'])->name('eleve.devoirs.remettre');
    });

    // ---------------------------------------------------------------- Cours et devoirs (enseignants ; consultation direction)
    Route::middleware('permission:cours.gerer|rapports.scolaires')->group(function () {
        Route::get('/cours', [Pedagogie\CoursController::class, 'index'])->name('cours.index');
        Route::get('/cours/create', [Pedagogie\CoursController::class, 'create'])->name('cours.create');
        Route::post('/cours', [Pedagogie\CoursController::class, 'store'])->name('cours.store');
        Route::get('/cours/{cours}', [Pedagogie\CoursController::class, 'show'])->name('cours.show');
        Route::get('/cours/{cours}/edit', [Pedagogie\CoursController::class, 'edit'])->name('cours.edit');
        Route::put('/cours/{cours}', [Pedagogie\CoursController::class, 'update'])->name('cours.update');
        Route::delete('/cours/{cours}', [Pedagogie\CoursController::class, 'destroy'])->name('cours.destroy');
        Route::post('/cours/{cours}/publier', [Pedagogie\CoursController::class, 'publier'])->name('cours.publier');
        Route::post('/cours/{cours}/ressources', [Pedagogie\CoursController::class, 'storeRessource'])->name('cours.ressources.store');
        Route::delete('/cours/{cours}/ressources/{ressource}', [Pedagogie\CoursController::class, 'destroyRessource'])->name('cours.ressources.destroy')->scopeBindings();

        Route::get('/devoirs/create', [Pedagogie\DevoirController::class, 'create'])->name('devoirs.create');
        Route::post('/devoirs', [Pedagogie\DevoirController::class, 'store'])->name('devoirs.store');
        Route::get('/devoirs/{devoir}', [Pedagogie\DevoirController::class, 'show'])->name('devoirs.show');
        Route::get('/devoirs/{devoir}/edit', [Pedagogie\DevoirController::class, 'edit'])->name('devoirs.edit');
        Route::put('/devoirs/{devoir}', [Pedagogie\DevoirController::class, 'update'])->name('devoirs.update');
        Route::delete('/devoirs/{devoir}', [Pedagogie\DevoirController::class, 'destroy'])->name('devoirs.destroy');
        Route::post('/devoirs/{devoir}/publier', [Pedagogie\DevoirController::class, 'publier'])->name('devoirs.publier');
        Route::post('/devoirs/{devoir}/corrections/{inscription}', [Pedagogie\DevoirController::class, 'corriger'])->name('devoirs.corriger');
    });
    // Fichiers : contrôle par les Policies (enseignant, direction, élève de la classe ; copie : l'élève concerné seulement).
    Route::get('/cours/{cours}/ressources/{ressource}/fichier', [Pedagogie\CoursController::class, 'fichier'])->name('cours.ressources.fichier')->scopeBindings();
    Route::get('/devoirs/{devoir}/sujet', [Pedagogie\DevoirController::class, 'sujet'])->name('devoirs.sujet');
    Route::get('/devoirs/{devoir}/copies/{remise}', [Pedagogie\DevoirController::class, 'copie'])->name('devoirs.copie');

    // ---------------------------------------------------------------- Scolarité
    Route::middleware('permission:classes.gerer')->group(function () {
        Route::get('/classes/create', [Scolarite\ClasseController::class, 'create'])->name('classes.create');
        Route::post('/classes', [Scolarite\ClasseController::class, 'store'])->name('classes.store');
        Route::post('/classes/dupliquer', [Scolarite\ClasseController::class, 'dupliquer'])->name('classes.dupliquer');
        Route::get('/classes/{classe}/edit', [Scolarite\ClasseController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{classe}', [Scolarite\ClasseController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{classe}', [Scolarite\ClasseController::class, 'destroy'])->name('classes.destroy');
    });
    Route::get('/classes', [Scolarite\ClasseController::class, 'index'])->middleware('permission:classes.gerer|eleves.consulter')->name('classes.index');
    // Policy : personnel autorisé ou enseignant intervenant dans la classe.
    Route::get('/classes/{classe}', [Scolarite\ClasseController::class, 'show'])->middleware('can:view,classe')->name('classes.show');

    Route::middleware('permission:eleves.gerer')->group(function () {
        Route::get('/eleves/create', [Scolarite\EleveController::class, 'create'])->name('eleves.create');
        Route::post('/eleves', [Scolarite\EleveController::class, 'store'])->name('eleves.store');
        Route::get('/eleves/{eleve}/edit', [Scolarite\EleveController::class, 'edit'])->name('eleves.edit');
        Route::put('/eleves/{eleve}', [Scolarite\EleveController::class, 'update'])->name('eleves.update');
        Route::delete('/eleves/{eleve}', [Scolarite\EleveController::class, 'destroy'])->name('eleves.destroy');
        Route::post('/eleves/{eleve}/archiver', [Scolarite\EleveController::class, 'archiver'])->name('eleves.archiver');
        Route::post('/eleves/{eleve}/desarchiver', [Scolarite\EleveController::class, 'desarchiver'])->name('eleves.desarchiver');
        Route::post('/eleves/{eleve}/compte', [Scolarite\EleveController::class, 'creerCompte'])->name('eleves.compte');

        Route::get('/eleves/{eleve}/responsables/create', [Scolarite\ResponsableController::class, 'create'])->name('responsables.create');
        Route::post('/eleves/{eleve}/responsables', [Scolarite\ResponsableController::class, 'store'])->name('responsables.store');
        Route::get('/eleves/{eleve}/responsables/{responsable}/edit', [Scolarite\ResponsableController::class, 'edit'])->name('responsables.edit');
        Route::put('/eleves/{eleve}/responsables/{responsable}', [Scolarite\ResponsableController::class, 'update'])->name('responsables.update');
        Route::delete('/eleves/{eleve}/responsables/{responsable}', [Scolarite\ResponsableController::class, 'destroy'])->name('responsables.destroy');
        Route::post('/eleves/{eleve}/responsables/{responsable}/compte', [Scolarite\ResponsableController::class, 'creerCompte'])->name('responsables.compte');

        Route::post('/eleves/{eleve}/documents', [Scolarite\DocumentEleveController::class, 'store'])->name('documents.store');
        Route::delete('/eleves/{eleve}/documents/{document}', [Scolarite\DocumentEleveController::class, 'destroy'])->name('documents.destroy')->scopeBindings();
    });
    Route::middleware('permission:eleves.consulter')->group(function () {
        Route::get('/eleves', [Scolarite\EleveController::class, 'index'])->name('eleves.index');
        Route::get('/eleves/{eleve}', [Scolarite\EleveController::class, 'show'])->name('eleves.show');
        Route::get('/eleves/{eleve}/documents/{document}', [Scolarite\DocumentEleveController::class, 'show'])->name('documents.show')->scopeBindings();
    });
    // Contrôle d'accès par la Policy (personnel, responsables, élève lui-même).
    Route::get('/eleves/{eleve}/photo', [Scolarite\EleveController::class, 'photo'])->name('eleves.photo');

    Route::middleware('permission:inscriptions.gerer')->group(function () {
        Route::get('/inscriptions', [Scolarite\InscriptionController::class, 'index'])->name('inscriptions.index');
        Route::get('/inscriptions/reinscriptions', [Scolarite\InscriptionController::class, 'reinscriptions'])->name('inscriptions.reinscriptions');
        Route::post('/inscriptions/reinscriptions', [Scolarite\InscriptionController::class, 'storeReinscriptions'])->name('inscriptions.reinscriptions.store');
        Route::post('/eleves/{eleve}/inscriptions', [Scolarite\InscriptionController::class, 'store'])->name('inscriptions.store');
        Route::post('/inscriptions/{inscription}/changer-classe', [Scolarite\InscriptionController::class, 'changerClasse'])->name('inscriptions.changer-classe');
        Route::post('/inscriptions/{inscription}/depart', [Scolarite\InscriptionController::class, 'depart'])->name('inscriptions.depart');
        Route::post('/inscriptions/{inscription}/annuler-depart', [Scolarite\InscriptionController::class, 'annulerDepart'])->name('inscriptions.annuler-depart');
    });

    // ---------------------------------------------------------------- Pédagogie : enseignants, affectations, emplois du temps
    Route::middleware('permission:enseignants.gerer')->group(function () {
        Route::get('/enseignants', [Pedagogie\EnseignantController::class, 'index'])->name('enseignants.index');
        Route::get('/enseignants/create', [Pedagogie\EnseignantController::class, 'create'])->name('enseignants.create');
        Route::post('/enseignants', [Pedagogie\EnseignantController::class, 'store'])->name('enseignants.store');
        Route::get('/enseignants/{enseignant}', [Pedagogie\EnseignantController::class, 'show'])->name('enseignants.show');
        Route::get('/enseignants/{enseignant}/edit', [Pedagogie\EnseignantController::class, 'edit'])->name('enseignants.edit');
        Route::put('/enseignants/{enseignant}', [Pedagogie\EnseignantController::class, 'update'])->name('enseignants.update');
    });

    Route::middleware('permission:affectations.gerer')->group(function () {
        Route::get('/affectations', [Pedagogie\AffectationController::class, 'index'])->name('affectations.index');
        Route::get('/affectations/classes/{classe}', [Pedagogie\AffectationController::class, 'classe'])->name('affectations.classe');
        Route::post('/affectations/classes/{classe}', [Pedagogie\AffectationController::class, 'store'])->name('affectations.store');
        Route::put('/affectations/classes/{classe}', [Pedagogie\AffectationController::class, 'update'])->name('affectations.update');
        Route::post('/affectations/classes/{classe}/copier', [Pedagogie\AffectationController::class, 'copier'])->name('affectations.copier');
        Route::delete('/affectations/classes/{classe}/{affectation}', [Pedagogie\AffectationController::class, 'destroy'])->name('affectations.destroy')->scopeBindings();
    });

    Route::get('/emplois-du-temps', [Pedagogie\EmploiDuTempsController::class, 'index'])->name('emplois-du-temps.index');
    Route::get('/emplois-du-temps/classes/{classe}', [Pedagogie\EmploiDuTempsController::class, 'classe'])->name('emplois-du-temps.classe');
    Route::get('/emplois-du-temps/enseignants/{enseignant}', [Pedagogie\EmploiDuTempsController::class, 'enseignant'])->name('emplois-du-temps.enseignant');
    Route::middleware('permission:emplois_du_temps.gerer')->group(function () {
        Route::post('/emplois-du-temps/classes/{classe}', [Pedagogie\EmploiDuTempsController::class, 'store'])->name('emplois-du-temps.store');
        Route::delete('/emplois-du-temps/creneaux/{creneau}', [Pedagogie\EmploiDuTempsController::class, 'destroy'])->name('emplois-du-temps.destroy');
    });

    Route::get('/mes-classes', Pedagogie\MesClassesController::class)->middleware('role:enseignant')->name('mes-classes.index');

    // Évaluations et notes : permissions par groupe, puis EvaluationPolicy (affectations de l'enseignant).
    Route::middleware('permission:notes.saisir|notes.consulter')->group(function () {
        Route::get('/evaluations', [Pedagogie\EvaluationController::class, 'index'])->name('evaluations.index');
        Route::get('/evaluations/create', [Pedagogie\EvaluationController::class, 'create'])->name('evaluations.create');
        Route::post('/evaluations', [Pedagogie\EvaluationController::class, 'store'])->name('evaluations.store');
        Route::get('/evaluations/{evaluation}', [Pedagogie\EvaluationController::class, 'show'])->name('evaluations.show');
        Route::get('/evaluations/{evaluation}/edit', [Pedagogie\EvaluationController::class, 'edit'])->name('evaluations.edit');
        Route::put('/evaluations/{evaluation}', [Pedagogie\EvaluationController::class, 'update'])->name('evaluations.update');
        Route::delete('/evaluations/{evaluation}', [Pedagogie\EvaluationController::class, 'destroy'])->name('evaluations.destroy');
        Route::put('/evaluations/{evaluation}/notes', [Pedagogie\EvaluationController::class, 'notes'])->name('evaluations.notes');
        Route::post('/evaluations/{evaluation}/valider', [Pedagogie\EvaluationController::class, 'validerEvaluation'])->name('evaluations.valider');
        Route::get('/resultats/affectations/{affectation}', [Pedagogie\ResultatController::class, 'affectation'])->name('resultats.affectation');
    });
    Route::post('/evaluations/{evaluation}/deverrouiller', [Pedagogie\EvaluationController::class, 'deverrouiller'])
        ->middleware('permission:evaluations.deverrouiller')->name('evaluations.deverrouiller');
    // Direction, vie scolaire ou professeur principal (vérifié dans le contrôleur).
    Route::get('/resultats', [Pedagogie\ResultatController::class, 'classe'])->middleware('permission:notes.consulter|notes.saisir')->name('resultats.classe');

    Route::get('/statistiques-scolaires', Pedagogie\RapportScolaireController::class)->middleware('permission:rapports.scolaires')->name('rapports.scolaires');

    // ---------------------------------------------------------------- Bulletins
    Route::middleware('permission:bulletins.consulter|bulletins.publier')->group(function () {
        Route::get('/bulletins', [Pedagogie\BulletinController::class, 'index'])->name('bulletins.index');
        Route::get('/bulletins/classes/{classe}/periodes/{periode}', [Pedagogie\BulletinController::class, 'classe'])->name('bulletins.classe');
        Route::get('/bulletins/classes/{classe}/periodes/{periode}/pdf', [Pedagogie\BulletinController::class, 'pdfClasse'])->name('bulletins.pdf-classe');
    });
    Route::middleware('permission:bulletins.publier')->group(function () {
        Route::post('/bulletins/classes/{classe}/periodes/{periode}/publier', [Pedagogie\BulletinController::class, 'publier'])->name('bulletins.publier');
        Route::post('/bulletins/classes/{classe}/periodes/{periode}/depublier', [Pedagogie\BulletinController::class, 'depublier'])->name('bulletins.depublier');
    });
    // Direction ou professeur principal (vérifié dans le contrôleur).
    Route::get('/bulletins/classes/{classe}/periodes/{periode}/appreciations', [Pedagogie\BulletinController::class, 'appreciations'])->name('bulletins.appreciations');
    Route::put('/bulletins/classes/{classe}/periodes/{periode}/appreciations', [Pedagogie\BulletinController::class, 'enregistrerAppreciations'])->name('bulletins.appreciations.update');
    // BulletinPolicy : personnel autorisé, ou famille / élève pour un bulletin publié.
    Route::get('/bulletins/{bulletin}/pdf', [Pedagogie\BulletinController::class, 'pdf'])->name('bulletins.pdf');

    // ---------------------------------------------------------------- Finances
    Route::middleware('permission:frais.configurer')->group(function () {
        Route::get('/finances/frais', [Finance\FraisController::class, 'index'])->name('grilles-frais.index');
        Route::get('/finances/frais/grilles/create', [Finance\FraisController::class, 'create'])->name('grilles-frais.create');
        Route::post('/finances/frais/grilles', [Finance\FraisController::class, 'store'])->name('grilles-frais.store');
        Route::get('/finances/frais/grilles/{grille}/edit', [Finance\FraisController::class, 'edit'])->name('grilles-frais.edit');
        Route::put('/finances/frais/grilles/{grille}', [Finance\FraisController::class, 'update'])->name('grilles-frais.update');
        Route::delete('/finances/frais/grilles/{grille}', [Finance\FraisController::class, 'destroy'])->name('grilles-frais.destroy');
        Route::post('/finances/frais/copier', [Finance\FraisController::class, 'copier'])->name('grilles-frais.copier');
        Route::post('/finances/frais/annees/{annee}/generer', [Finance\FraisController::class, 'generer'])->name('grilles-frais.generer');
        Route::post('/finances/frais/types', [Finance\FraisController::class, 'storeType'])->name('types-frais.store');
        Route::put('/finances/frais/types/{type}', [Finance\FraisController::class, 'updateType'])->name('types-frais.update');

        Route::post('/finances/eleves/{inscription}/frais', [Finance\SituationController::class, 'attribuer'])->name('finances.attribuer');
        Route::delete('/finances/eleves/{inscription}/frais', [Finance\SituationController::class, 'retirer'])->name('finances.retirer');
        Route::post('/finances/eleves/{inscription}/generer', [Finance\SituationController::class, 'generer'])->name('finances.generer');
    });

    Route::middleware('permission:finances.consulter|paiements.enregistrer')->group(function () {
        Route::get('/finances/eleves', [Finance\SituationController::class, 'index'])->name('finances.index');
        Route::get('/finances/eleves/{inscription}', [Finance\SituationController::class, 'show'])->name('finances.show');
        Route::get('/finances/paiements', [Finance\PaiementController::class, 'index'])->name('paiements.index');
    });
    Route::post('/finances/eleves/{inscription}/paiements', [Finance\PaiementController::class, 'store'])
        ->middleware('permission:paiements.enregistrer')->name('paiements.store');
    Route::post('/finances/paiements/{paiement}/annuler', [Finance\PaiementController::class, 'annuler'])
        ->middleware('permission:paiements.annuler')->name('paiements.annuler');
    // PaiementPolicy : personnel financier, ou famille / élève.
    Route::get('/finances/paiements/{paiement}/recu', [Finance\PaiementController::class, 'recu'])->name('paiements.recu');

    Route::middleware('permission:remises.accorder')->group(function () {
        Route::post('/finances/frais-eleves/{frais}/remises', [Finance\RemiseController::class, 'store'])->name('remises.store');
        Route::post('/finances/remises/{remise}/annuler', [Finance\RemiseController::class, 'annuler'])->name('remises.annuler');
    });

    Route::middleware('permission:depenses.gerer|depenses.annuler|rapports.financiers')->group(function () {
        Route::get('/finances/depenses', [Finance\DepenseController::class, 'index'])->name('depenses.index');
        Route::get('/finances/depenses/{depense}/justificatif', [Finance\DepenseController::class, 'justificatif'])->name('depenses.justificatif');
    });
    Route::middleware('permission:depenses.gerer')->group(function () {
        Route::get('/finances/depenses/create', [Finance\DepenseController::class, 'create'])->name('depenses.create');
        Route::post('/finances/depenses', [Finance\DepenseController::class, 'store'])->name('depenses.store');
        Route::post('/finances/depenses/categories', [Finance\DepenseController::class, 'storeCategorie'])->name('categories-depenses.store');
    });
    Route::post('/finances/depenses/{depense}/annuler', [Finance\DepenseController::class, 'annuler'])
        ->middleware('permission:depenses.annuler')->name('depenses.annuler');

    Route::get('/finances/rapports', Finance\RapportFinancierController::class)->middleware('permission:rapports.financiers')->name('rapports.financiers');

    // ---------------------------------------------------------------- Vie scolaire
    Route::middleware('permission:presences.saisir|presences.saisir_ses_classes|presences.consulter')->group(function () {
        Route::get('/appels', [VieScolaire\AppelController::class, 'index'])->name('appels.index');
        Route::get('/appels/classes/{classe}', [VieScolaire\AppelController::class, 'saisie'])->name('appels.saisie');
    });
    Route::post('/appels/classes/{classe}', [VieScolaire\AppelController::class, 'store'])
        ->middleware('permission:presences.saisir|presences.saisir_ses_classes')->name('appels.store');

    Route::middleware('permission:presences.justifier')->group(function () {
        Route::get('/justificatifs', [VieScolaire\JustificatifController::class, 'index'])->name('justificatifs.index');
        Route::get('/justificatifs/create', [VieScolaire\JustificatifController::class, 'create'])->name('justificatifs.create');
        Route::post('/justificatifs', [VieScolaire\JustificatifController::class, 'store'])->name('justificatifs.store');
        Route::delete('/justificatifs/{justificatif}', [VieScolaire\JustificatifController::class, 'destroy'])->name('justificatifs.destroy');
    });
    Route::get('/justificatifs/{justificatif}/fichier', [VieScolaire\JustificatifController::class, 'fichier'])
        ->middleware('permission:presences.justifier|presences.consulter')->name('justificatifs.fichier');

    // ---------------------------------------------------------------- Administration
    Route::middleware('permission:etablissement.gerer')->group(function () {
        Route::get('/administration/etablissement', [Admin\EtablissementController::class, 'edit'])->name('etablissement.edit');
        Route::put('/administration/etablissement', [Admin\EtablissementController::class, 'update'])->name('etablissement.update');

        Route::resource('administration/annees', Admin\AnneeScolaireController::class)
            ->parameters(['annees' => 'annee'])->except('destroy')->names('annees');
        Route::post('/administration/annees/{annee}/activer', [Admin\AnneeScolaireController::class, 'activer'])->name('annees.activer');
        Route::post('/administration/annees/{annee}/cloturer', [Admin\AnneeScolaireController::class, 'cloturer'])->name('annees.cloturer');
        Route::post('/administration/annees/{annee}/periodes', [Admin\PeriodeController::class, 'store'])->name('periodes.store');
        Route::put('/administration/annees/{annee}/periodes/{periode}', [Admin\PeriodeController::class, 'update'])->name('periodes.update')->scopeBindings();
        Route::post('/administration/annees/{annee}/periodes/{periode}/cloturer', [Admin\PeriodeController::class, 'cloturer'])->name('periodes.cloturer')->scopeBindings();
        Route::post('/administration/annees/{annee}/periodes/{periode}/rouvrir', [Admin\PeriodeController::class, 'rouvrir'])->name('periodes.rouvrir')->scopeBindings();

        Route::get('/administration/referentiel', [Admin\ReferentielController::class, 'index'])->name('referentiel.index');
        Route::post('/administration/niveaux', [Admin\ReferentielController::class, 'storeNiveau'])->name('niveaux.store');
        Route::put('/administration/niveaux/{niveau}', [Admin\ReferentielController::class, 'updateNiveau'])->name('niveaux.update');
        Route::delete('/administration/niveaux/{niveau}', [Admin\ReferentielController::class, 'destroyNiveau'])->name('niveaux.destroy');
        Route::post('/administration/matieres', [Admin\ReferentielController::class, 'storeMatiere'])->name('matieres.store');
        Route::put('/administration/matieres/{matiere}', [Admin\ReferentielController::class, 'updateMatiere'])->name('matieres.update');
        Route::delete('/administration/matieres/{matiere}', [Admin\ReferentielController::class, 'destroyMatiere'])->name('matieres.destroy');
    });

    Route::middleware('permission:utilisateurs.gerer')->group(function () {
        Route::resource('administration/utilisateurs', Admin\UtilisateurController::class)
            ->parameters(['utilisateurs' => 'utilisateur'])->except(['show', 'destroy'])->names('utilisateurs');
        Route::post('/administration/utilisateurs/{utilisateur}/reinitialiser', [Admin\UtilisateurController::class, 'reinitialiser'])->name('utilisateurs.reinitialiser');
    });

    Route::get('/administration/journal', [Admin\JournalController::class, 'index'])
        ->middleware('permission:journal.consulter')->name('journal.index');
});
