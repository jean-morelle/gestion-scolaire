<?php

namespace Database\Seeders;

use App\Enums\Creneau;
use App\Enums\Cycle;
use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutEvaluation;
use App\Enums\StatutPeriode;
use App\Enums\TypeEvaluation;
use App\Enums\TypeRessource;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\CategorieDepense;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\GrilleFrais;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Presence;
use App\Models\Responsable;
use App\Models\Tranche;
use App\Models\TypeFrais;
use App\Models\User;
use App\Services\AnnonceService;
use App\Services\AppelService;
use App\Services\ApprentissageService;
use App\Services\BulletinService;
use App\Services\DepenseService;
use App\Services\EleveService;
use App\Services\InscriptionService;
use App\Services\JustificatifService;
use App\Services\PaiementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DONNÉES DE DÉMONSTRATION ENTIÈREMENT FICTIVES (établissement, personnes, notes, paiements).
 *
 * À charger uniquement dans une base dédiée dont le nom contient « demo » :
 *   php artisan ecole:demo
 * Toutes les données passent par les services métier, donc respectent les mêmes règles que l'application.
 * Tous les comptes ont le mot de passe « Demo2026 » (voir docs/installation-xampp.md).
 */
class DemonstrationSeeder extends Seeder
{
    public const MOT_DE_PASSE = 'Demo2026';

    private const NOMS = ['AGBEKO', 'AMEGAN', 'ADJOVI', 'KOFFI', 'MENSAH', 'LAWSON', 'AKAKPO', 'DOSSOU', 'KPODAR', 'ATAYI', 'SOSSOU', 'GBADOE',
        'HOUNKPATI', 'TCHALLA', 'KOMLAN', 'ABALO', 'ESSO', 'BATCHO', 'KODJO', 'DJOSSOU', 'AYENA', 'FIAWOO', 'SEGLA', 'NOUWAVI'];

    private const PRENOMS_F = ['Ama', 'Afi', 'Akossiwa', 'Adjoa', 'Enyonam', 'Esi', 'Yawa', 'Dzifa', 'Mawuena', 'Sena', 'Abla', 'Kafui', 'Edem', 'Elom'];

    private const PRENOMS_M = ['Kofi', 'Kodjo', 'Komlan', 'Yao', 'Koffi', 'Kwami', 'Mawuli', 'Selom', 'Edoh', 'Kossi', 'Yawo', 'Mensah', 'Dodzi', 'Elikem'];

    private CarbonImmutable $aujourdhui;

    public function run(): void
    {
        $base = DB::connection()->getDatabaseName();
        if (! app()->runningUnitTests() && ! str_contains(mb_strtolower((string) $base), 'demo')) {
            throw new RuntimeException("Refus : la base « {$base} » n'est pas une base de démonstration (son nom doit contenir « demo »).");
        }

        mt_srand(2026);
        $this->aujourdhui = CarbonImmutable::today();
        $this->call([ReferentielSeeder::class, RolesEtPermissionsSeeder::class]);

        Etablissement::courant()->update([
            'nom' => 'Collège Les Palmiers (DÉMONSTRATION)',
            'sigle' => 'CLP-DEMO',
            'ville' => 'Lomé',
            'adresse' => 'Adresse fictive',
            'telephone' => '00 00 00 00',
            'email' => 'contact@demo.test',
            'slogan' => 'Données fictives — ne pas utiliser en production',
            'chef_etablissement' => 'M. Directeur Démo',
        ]);

        $comptes = $this->comptes();
        auth()->setUser($comptes['directeur']);

        [$annee, $periodes] = $this->annee();
        $enseignants = $this->enseignants();
        $classes = $this->classes($annee, $enseignants);
        $this->frais($annee);
        $inscriptions = $this->eleves($classes, $comptes['secretaire']);
        $this->notes($classes, $periodes[0]);
        $this->appels($classes, $comptes['censeur']);
        $this->paiements($inscriptions, $comptes['comptable']);
        $this->depenses($comptes['comptable']);
        $this->apprentissage($classes, $inscriptions);

        // Bulletins du 1er trimestre publiés pour la 6e A (classe de démonstration complète).
        app(BulletinService::class)->publier($classes['6e A'], $periodes[0], $comptes['directeur'], forcer: true);

        app(AnnonceService::class)->publier(['titre' => 'Bienvenue sur la démonstration', 'cible' => 'tous',
            'contenu' => "Cet établissement et toutes ses données sont fictifs.\nExplorez l'application avec les différents comptes de démonstration."], $comptes['directeur']);
        app(AnnonceService::class)->publier(['titre' => 'Réunion parents-professeurs (6e A)', 'cible' => 'parents', 'classe_id' => $classes['6e A']->id,
            'contenu' => 'Rencontre avec l\'équipe pédagogique de la 6e A, samedi à 9 h.'], $comptes['directeur']);

        auth()->forgetUser();
    }

    /**
     * @return array<string, User>
     */
    private function comptes(): array
    {
        $creer = function (string $nom, string $email, Role ...$roles) {
            $user = User::create(['name' => $nom, 'email' => $email, 'password' => self::MOT_DE_PASSE, 'est_actif' => true, 'doit_changer_mot_de_passe' => false]);
            $user->syncRoles(array_map(fn (Role $r) => $r->value, $roles));

            return $user;
        };

        return [
            'admin' => $creer('Administrateur Démo', 'admin@demo.test', Role::Administrateur),
            'directeur' => $creer('Directeur Démo', 'direction@demo.test', Role::Directeur),
            'secretaire' => $creer('Secrétaire Démo', 'secretariat@demo.test', Role::Secretaire),
            'censeur' => $creer('Censeur Démo', 'censeur@demo.test', Role::Censeur),
            'comptable' => $creer('Comptable Démo', 'comptabilite@demo.test', Role::Comptable),
        ];
    }

    /**
     * Année en cours (septembre à juillet), trois trimestres.
     *
     * @return array{0: AnneeScolaire, 1: list<Periode>}
     */
    private function annee(): array
    {
        $debut = $this->aujourdhui->month >= 8 ? $this->aujourdhui->year : $this->aujourdhui->year - 1;
        $annee = AnneeScolaire::create(['libelle' => $debut.'-'.($debut + 1), 'date_debut' => "{$debut}-09-01", 'date_fin' => ($debut + 1).'-07-10',
            'est_active' => true, 'statut' => 'en_cours']);
        $periodes = [];
        foreach ([['1er trimestre', "{$debut}-09-01", "{$debut}-11-30"], ['2e trimestre', "{$debut}-12-01", ($debut + 1).'-03-15'], ['3e trimestre', ($debut + 1).'-03-16', ($debut + 1).'-07-10']] as $i => [$libelle, $du, $au]) {
            $periodes[] = Periode::create(['annee_scolaire_id' => $annee->id, 'libelle' => $libelle, 'ordre' => $i + 1, 'date_debut' => $du, 'date_fin' => $au, 'statut' => StatutPeriode::Ouverte]);
        }

        return [$annee, $periodes];
    }

    /**
     * @return array<string, Enseignant> code matière => enseignant
     */
    private function enseignants(): array
    {
        $liste = ['MATH' => 'M. Komlan AGBEKO', 'FR' => 'Mme Afi LAWSON', 'ANG' => 'Mme Sena MENSAH', 'PC' => 'M. Kossi DOSSOU',
            'SVT' => 'Mme Abla KPODAR', 'HG' => 'M. Yawo ATAYI', 'EPS' => 'M. Edoh TCHALLA'];
        $enseignants = [];
        foreach ($liste as $code => $nom) {
            $user = User::create(['name' => $nom, 'email' => 'prof.'.mb_strtolower($code).'@demo.test', 'password' => self::MOT_DE_PASSE,
                'est_actif' => true, 'doit_changer_mot_de_passe' => false]);
            $user->syncRoles([Role::Enseignant->value]);
            $enseignant = Enseignant::create(['user_id' => $user->id, 'matricule' => 'ENS-'.$code, 'specialite' => Matiere::where('code', $code)->value('libelle'), 'est_actif' => true]);
            $enseignant->matieres()->sync([Matiere::where('code', $code)->value('id')]);
            $enseignants[$code] = $enseignant;
        }

        return $enseignants;
    }

    /**
     * @param  array<string, Enseignant>  $enseignants
     * @return array<string, Classe>
     */
    private function classes(AnneeScolaire $annee, array $enseignants): array
    {
        $coefficients = ['MATH' => 4, 'FR' => 4, 'ANG' => 2, 'PC' => 2, 'SVT' => 2, 'HG' => 2, 'EPS' => 1];
        $definitions = [['6e A', '6e', 'MATH'], ['6e B', '6e', 'FR'], ['5e A', '5e', 'SVT'], ['3e A', '3e', 'HG']];
        $classes = [];
        foreach ($definitions as [$nom, $niveau, $principal]) {
            $classe = Classe::create(['annee_scolaire_id' => $annee->id, 'niveau_id' => Niveau::where('libelle', $niveau)->value('id'),
                'nom' => $nom, 'capacite' => 45, 'professeur_principal_id' => $enseignants[$principal]->id]);
            $ordre = 1;
            foreach ($coefficients as $code => $coefficient) {
                Affectation::create(['classe_id' => $classe->id, 'matiere_id' => Matiere::where('code', $code)->value('id'),
                    'enseignant_id' => $enseignants[$code]->id, 'coefficient' => $coefficient, 'ordre_bulletin' => $ordre++]);
            }
            $classes[$nom] = $classe;
        }

        // Emploi du temps hebdomadaire de la 6e A (et créneaux de maths des autres classes, sans conflit).
        $creneaux = [[1, '07:30', '09:30', 'MATH'], [1, '09:45', '11:45', 'FR'], [2, '07:30', '09:30', 'ANG'], [2, '09:45', '11:45', 'PC'],
            [3, '07:30', '09:30', 'SVT'], [3, '09:45', '11:45', 'MATH'], [4, '07:30', '09:30', 'HG'], [4, '09:45', '11:45', 'FR'], [5, '07:30', '09:30', 'EPS'], [5, '09:45', '11:45', 'MATH']];
        foreach ($creneaux as [$jour, $debut, $fin, $code]) {
            $affectation = $classes['6e A']->affectations()->whereHas('matiere', fn ($q) => $q->where('code', $code))->first();
            EmploiDuTemps::create(['affectation_id' => $affectation->id, 'jour' => $jour, 'heure_debut' => $debut.':00', 'heure_fin' => $fin.':00', 'salle' => 'A1']);
        }
        foreach (['6e B' => 2, '5e A' => 4, '3e A' => 5] as $nom => $jour) {
            $affectation = $classes[$nom]->affectations()->whereHas('matiere', fn ($q) => $q->where('code', 'MATH'))->first();
            EmploiDuTemps::create(['affectation_id' => $affectation->id, 'jour' => $jour, 'heure_debut' => '07:30:00', 'heure_fin' => '09:30:00', 'salle' => 'B2']);
        }

        return $classes;
    }

    private function frais(AnneeScolaire $annee): void
    {
        $y = $annee->date_debut->year;
        $inscription = TypeFrais::where('code', 'INSCRIPTION')->firstOrFail();
        $scolarite = TypeFrais::where('code', 'SCOLARITE')->firstOrFail();
        $cantine = TypeFrais::firstOrCreate(['code' => 'CANTINE'], ['libelle' => 'Cantine', 'est_obligatoire' => false]);

        foreach (Niveau::where('cycle', Cycle::College)->get() as $niveau) {
            GrilleFrais::create(['annee_scolaire_id' => $annee->id, 'niveau_id' => $niveau->id, 'type_frais_id' => $inscription->id, 'montant' => 25000]);
            $total = $niveau->libelle === '3e' ? 105000 : 90000;
            $grille = GrilleFrais::create(['annee_scolaire_id' => $annee->id, 'niveau_id' => $niveau->id, 'type_frais_id' => $scolarite->id, 'montant' => $total]);
            foreach ([['1re tranche', 0.4, "{$y}-10-15"], ['2e tranche', 0.3, ($y + 1).'-01-15'], ['3e tranche', 0.3, ($y + 1).'-04-15']] as $i => [$libelle, $part, $date]) {
                Tranche::create(['grille_frais_id' => $grille->id, 'libelle' => $libelle, 'montant' => (int) round($total * $part), 'date_limite' => $date, 'ordre' => $i + 1]);
            }
            GrilleFrais::create(['annee_scolaire_id' => $annee->id, 'niveau_id' => $niveau->id, 'type_frais_id' => $cantine->id, 'montant' => 45000]);
        }
    }

    /**
     * @param  array<string, Classe>  $classes
     * @return list<Inscription>
     */
    private function eleves(array $classes, User $secretaire): array
    {
        auth()->setUser($secretaire);
        $eleves = app(EleveService::class);
        $inscrire = app(InscriptionService::class);
        $inscriptions = [];
        $telephone = 90000000;
        $n = 0;

        foreach ($classes as $classe) {
            $age = 11 + ['6e' => 0, '5e' => 1, '3e' => 3][$classe->niveau->libelle];
            for ($i = 0; $i < 14; $i++) {
                $fille = $n % 2 === 0;
                $nom = self::NOMS[($n * 7 + $i) % count(self::NOMS)];
                $prenoms = $fille ? self::PRENOMS_F[$n % count(self::PRENOMS_F)] : self::PRENOMS_M[$n % count(self::PRENOMS_M)];
                // Un élève sur sept a un frère ou une sœur déjà inscrit (responsable partagé).
                $fratrie = $n > 0 && $n % 7 === 0;
                $responsable = $fratrie
                    ? ['id' => Responsable::query()->latest('id')->value('id')]
                    : ['nom' => $nom, 'prenoms' => $fille ? 'Kodjo' : 'Akossiwa', 'telephone' => (string) ++$telephone, 'profession' => 'Commerçant(e)'];

                $eleve = $eleves->creer([
                    'nom' => $nom, 'prenoms' => $prenoms, 'sexe' => $fille ? 'F' : 'M', 'lieu_naissance' => 'Lomé', 'nationalite' => 'Togolaise',
                    'date_naissance' => $this->aujourdhui->subYears($age)->subDays(mt_rand(0, 360))->toDateString(),
                ], $responsable, ['lien' => $fille ? 'pere' : 'mere']);
                $inscriptions[] = $inscrire->inscrire($eleve, $classe, [
                    'date_inscription' => $classe->anneeScolaire->date_debut->subDays(mt_rand(5, 40))->toDateString(),
                    'redoublant' => $i === 13,
                ]);
                $n++;
            }
        }

        // Comptes de démonstration : un parent (par téléphone) et un élève (par matricule) de la 6e A.
        $premiere = $inscriptions[0];
        $responsable = $premiere->eleve->responsables()->first();
        $eleves->creerCompteParent($responsable);
        $responsable->user->forceFill(['password' => self::MOT_DE_PASSE, 'doit_changer_mot_de_passe' => false])->save();
        $eleves->creerCompteEleve($premiere->eleve);
        $premiere->eleve->fresh()->user->forceFill(['password' => self::MOT_DE_PASSE, 'doit_changer_mot_de_passe' => false])->save();

        return $inscriptions;
    }

    /**
     * Évaluations validées du 1er trimestre, notes réalistes (profil propre à chaque élève).
     *
     * @param  array<string, Classe>  $classes
     */
    private function notes(array $classes, Periode $trimestre): void
    {
        $fin = $trimestre->date_fin->lt($this->aujourdhui) ? $trimestre->date_fin : $this->aujourdhui->subDay();
        foreach ($classes as $classe) {
            $inscriptions = $classe->inscriptions()->get();
            $niveaux = $inscriptions->mapWithKeys(fn ($i) => [$i->id => mt_rand(70, 170) / 10]);
            foreach ($classe->affectations()->with('enseignant')->get() as $affectation) {
                foreach ([['Devoir n°1', TypeEvaluation::Devoir, 20, 1], ['Interrogation', TypeEvaluation::Interrogation, 10, 1], ['Composition', TypeEvaluation::Composition, 20, 2]] as $k => [$libelle, $type, $bareme, $poids]) {
                    $date = CarbonImmutable::parse($trimestre->date_debut)->addDays(14 + $k * 21);
                    if ($date->gt($fin)) {
                        $date = $fin;
                    }
                    // La 6e B a encore une composition en brouillon (pour voir le circuit de validation).
                    $brouillon = $classe->nom === '6e B' && $k === 2;
                    $evaluation = Evaluation::create(['affectation_id' => $affectation->id, 'periode_id' => $trimestre->id, 'libelle' => $libelle, 'type' => $type,
                        'date_evaluation' => $date, 'bareme' => $bareme, 'poids' => $poids,
                        'statut' => $brouillon ? StatutEvaluation::Brouillon : StatutEvaluation::Validee,
                        'validee_at' => $brouillon ? null : $date->addDays(3), 'created_by' => $affectation->enseignant->user_id]);
                    foreach ($inscriptions as $inscription) {
                        $absent = mt_rand(1, 40) === 1;
                        $sur20 = max(0, min(20, $niveaux[$inscription->id] + mt_rand(-35, 35) / 10));
                        Note::create(['evaluation_id' => $evaluation->id, 'inscription_id' => $inscription->id, 'est_absent' => $absent,
                            'valeur' => $absent ? null : round($sur20 * $bareme / 20 * 4) / 4]);
                    }
                }
            }
        }
    }

    /**
     * Appels des derniers jours de classe (environ 4 % d'absences), avec quelques justificatifs.
     *
     * @param  array<string, Classe>  $classes
     */
    private function appels(array $classes, User $censeur): void
    {
        auth()->setUser($censeur);
        $service = app(AppelService::class);
        $jour = $this->aujourdhui->subDay();
        $faits = 0;
        while ($faits < 8 && $jour->gte($classes['6e A']->anneeScolaire->date_debut)) {
            if (! $jour->isSunday() && ! $jour->isSaturday()) {
                foreach ($classes as $classe) {
                    foreach ([Creneau::Matin, Creneau::ApresMidi] as $creneau) {
                        $lignes = [];
                        foreach ($service->eleves($classe, $jour->toDateString()) as $inscription) {
                            $tirage = mt_rand(1, 100);
                            $lignes[$inscription->id] = $tirage <= 4 ? ['statut' => 'absent'] : ($tirage <= 7 ? ['statut' => 'retard', 'minutes_retard' => mt_rand(5, 30)] : ['statut' => 'present']);
                        }
                        $service->enregistrer($classe, $jour->toDateString(), $creneau, $lignes, $censeur);
                    }
                }
                $faits++;
            }
            $jour = $jour->subDay();
        }

        // Quelques absences justifiées.
        $justificatifs = app(JustificatifService::class);
        $absences = Presence::query()->where('statut', 'absent')->with('appel', 'inscription.eleve')->limit(5)->get();
        foreach ($absences as $presence) {
            $date = $presence->appel->date_appel->toDateString();
            $justificatifs->enregistrer($presence->inscription, $date, $date, 'Maladie (certificat médical présenté)', null);
        }
    }

    /**
     * Profils de paiement variés : soldé, à jour, partiel, en retard, rien payé.
     *
     * @param  list<Inscription>  $inscriptions
     */
    private function paiements(array $inscriptions, User $comptable): void
    {
        auth()->setUser($comptable);
        $service = app(PaiementService::class);
        $modes = [ModePaiement::Especes, ModePaiement::Especes, ModePaiement::MobileMoney, ModePaiement::Virement];
        foreach ($inscriptions as $k => $inscription) {
            $reste = (int) $inscription->fraisEleves()->sum('montant_du');
            $part = match ($k % 5) { 0 => 1.0, 1 => 0.7, 2 => 0.45, 3 => 0.2, default => 0.0 };
            $montant = (int) (floor($reste * $part / 5000) * 5000);
            $versements = $montant > 60000 ? [(int) ($montant / 2 / 5000) * 5000] : [];
            $versements[] = $montant - array_sum($versements);
            foreach ($versements as $v => $somme) {
                if ($somme <= 0) {
                    continue;
                }
                $mode = $modes[($k + $v) % count($modes)];
                $date = CarbonImmutable::parse($inscription->date_inscription)->addDays(10 + 25 * $v);
                $service->encaisser($inscription, $somme, ($date->gt($this->aujourdhui) ? $this->aujourdhui : $date)->toDateString(), $mode,
                    $mode === ModePaiement::Especes ? null : 'REF-'.mt_rand(100000, 999999), $inscription->eleve->responsables()->first()?->nom_complet, $comptable);
            }
        }
    }

    private function depenses(User $comptable): void
    {
        $service = app(DepenseService::class);
        $categorie = fn (string $l) => CategorieDepense::where('libelle', $l)->value('id');
        foreach ([['Fournitures et consommables', 'Craies, cahiers et marqueurs', 85000], ['Eau, électricité, internet', 'Facture d\'électricité', 120000],
            ['Entretien et réparations', 'Réparation de tables-bancs', 60000], ['Activités pédagogiques', 'Matériel de laboratoire', 95000]] as $i => [$cat, $libelle, $montant]) {
            $service->enregistrer(['categorie_depense_id' => $categorie($cat), 'libelle' => $libelle, 'montant' => $montant,
                'date_depense' => $this->aujourdhui->subDays(3 + $i * 6)->toDateString(), 'mode' => $i % 2 ? 'virement' : 'especes',
                'beneficiaire' => 'Fournisseur fictif '.($i + 1)], null, $comptable);
        }
    }

    /**
     * Un cours publié et un devoir avec quelques remises en mathématiques (6e A).
     *
     * @param  array<string, Classe>  $classes
     * @param  list<Inscription>  $inscriptions
     */
    private function apprentissage(array $classes, array $inscriptions): void
    {
        $affectation = $classes['6e A']->affectations()->whereHas('matiere', fn ($q) => $q->where('code', 'MATH'))->with('enseignant.user')->first();
        $prof = $affectation->enseignant->user;
        auth()->setUser($prof);
        $service = app(ApprentissageService::class);

        $cours = $service->creerCours($affectation, ['titre' => 'Les fractions', 'description' => 'Comprendre, comparer et additionner des fractions.'], $prof);
        $service->ajouterRessource($cours, ['type' => TypeRessource::Chapitre->value, 'titre' => '1. Qu\'est-ce qu\'une fraction ?',
            'contenu' => "Une fraction représente une partie d'un tout.\nDans 3/4, 4 est le dénominateur (le nombre de parts égales) et 3 le numérateur (le nombre de parts prises)."], null);
        $service->ajouterRessource($cours, ['type' => TypeRessource::Exercice->value, 'titre' => 'Exercices d\'entraînement',
            'contenu' => "1) Simplifier 6/8.\n2) Comparer 2/3 et 3/5.\n3) Calculer 1/4 + 2/4."], null);
        $service->ajouterRessource($cours, ['type' => TypeRessource::Lien->value, 'titre' => 'Rappel en vidéo (lien externe fictif)', 'url' => 'https://example.org/fractions'], null);
        $service->publierCours($cours, true);

        $devoir = $service->enregistrerDevoir($affectation, ['titre' => 'Exercices sur les fractions', 'consignes' => 'Faire les exercices 1 à 3 du cours et justifier chaque réponse.',
            'cours_id' => $cours->id, 'date_limite' => $this->aujourdhui->addDays(5)->setTime(18, 0), 'bareme' => 20, 'remise_en_ligne' => true, 'accepte_retard' => false], null, $prof);
        $service->publierDevoir($devoir);
        foreach (array_slice(array_filter($inscriptions, fn ($i) => $i->classe_id === $classes['6e A']->id), 0, 4) as $k => $inscription) {
            $service->remettre($devoir->fresh(), $inscription, null, "Réponses de l'élève (texte fictif) : 6/8 = 3/4 ; 2/3 > 3/5 ; 1/4 + 2/4 = 3/4.");
            if ($k < 2) {
                $service->corriger($devoir, $inscription, 14 + $k * 3, 'Bon travail, justifiez davantage la comparaison.', $prof);
            }
        }
    }
}
