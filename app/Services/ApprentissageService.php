<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Enums\TypeRessource;
use App\Models\Affectation;
use App\Models\Cours;
use App\Models\Devoir;
use App\Models\Inscription;
use App\Models\RemiseDevoir;
use App\Models\RessourcePedagogique;
use App\Models\User;
use App\Notifications\DevoirPublie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Cours, ressources, devoirs et travaux remis. Tous les fichiers sont sur le disque privé « local »
 * et ne sont servis qu'après contrôle d'accès (CoursPolicy, DevoirPolicy).
 */
class ApprentissageService
{
    /** Types acceptés pour les ressources de cours et les sujets de devoirs. */
    public const MIMES_RESSOURCES = 'pdf,doc,docx,odt,xls,xlsx,ods,ppt,pptx,odp,txt,jpg,jpeg,png,mp3,mp4';

    /** Types acceptés pour les copies des élèves. */
    public const MIMES_COPIES = 'pdf,doc,docx,odt,txt,jpg,jpeg,png';

    public function __construct(private JournalService $journal) {}

    public function creerCours(Affectation $affectation, array $donnees, User $auteur): Cours
    {
        $this->verifierAnnee($affectation);
        $cours = Cours::create($donnees + ['affectation_id' => $affectation->id, 'created_by' => $auteur->id, 'est_publie' => false]);
        $this->journal->enregistrer('cours.cree', $cours, "Cours « {$cours->titre} » créé ({$affectation->libelle()})");

        return $cours;
    }

    public function publierCours(Cours $cours, bool $publier): void
    {
        $cours->update(['est_publie' => $publier, 'publie_at' => $publier ? ($cours->publie_at ?? now()) : $cours->publie_at]);
        $this->journal->enregistrer($publier ? 'cours.publie' : 'cours.retire', $cours, "Cours « {$cours->titre} » ".($publier ? 'publié' : 'retiré'));
    }

    public function supprimerCours(Cours $cours): void
    {
        if ($cours->devoirs()->exists()) {
            throw ValidationException::withMessages(['cours' => 'Des devoirs sont rattachés à ce cours : supprimez-les ou détachez-les d\'abord.']);
        }
        DB::transaction(function () use ($cours) {
            $titre = $cours->titre;
            $affectation = $cours->affectation;
            $cours->ressources()->delete();
            $cours->delete();
            Storage::disk('local')->deleteDirectory("cours/{$cours->id}");
            $this->journal->enregistrer('cours.supprime', $affectation, "Cours « {$titre} » supprimé");
        });
    }

    /**
     * @param  array{type: string, titre: string, contenu?: ?string, url?: ?string}  $donnees
     */
    public function ajouterRessource(Cours $cours, array $donnees, ?UploadedFile $fichier): RessourcePedagogique
    {
        $type = TypeRessource::from($donnees['type']);
        if ($type === TypeRessource::Document && ! $fichier) {
            throw ValidationException::withMessages(['fichier' => 'Joignez le document.']);
        }
        if ($type === TypeRessource::Lien && blank($donnees['url'] ?? null)) {
            throw ValidationException::withMessages(['url' => 'Indiquez l\'adresse du lien.']);
        }
        if (in_array($type, [TypeRessource::Chapitre, TypeRessource::Exercice], true) && blank($donnees['contenu'] ?? null) && ! $fichier) {
            throw ValidationException::withMessages(['contenu' => 'Rédigez le contenu ou joignez un fichier.']);
        }

        $ressource = new RessourcePedagogique([
            'cours_id' => $cours->id,
            'type' => $type,
            'titre' => $donnees['titre'],
            'contenu' => $type === TypeRessource::Lien || $type === TypeRessource::Document ? null : ($donnees['contenu'] ?? null),
            'url' => $type === TypeRessource::Lien ? $donnees['url'] : null,
            'ordre' => (int) $cours->ressources()->max('ordre') + 1,
        ]);
        if ($fichier && $type !== TypeRessource::Lien) {
            $ressource->fill($this->stocker($fichier, "cours/{$cours->id}"));
        }
        $ressource->save();
        $cours->touch();

        return $ressource;
    }

    public function supprimerRessource(RessourcePedagogique $ressource): void
    {
        if ($ressource->fichier_chemin) {
            Storage::disk('local')->delete($ressource->fichier_chemin);
        }
        $ressource->delete();
    }

    public function enregistrerDevoir(Affectation $affectation, array $donnees, ?UploadedFile $sujet, User $auteur, ?Devoir $devoir = null): Devoir
    {
        $this->verifierAnnee($affectation);
        if ($devoir && $devoir->remises()->exists() && (float) $donnees['bareme'] < (float) $devoir->remises()->max('note')) {
            throw ValidationException::withMessages(['bareme' => 'Des copies ont déjà une note supérieure à ce barème.']);
        }

        return DB::transaction(function () use ($affectation, $donnees, $sujet, $auteur, $devoir) {
            $nouveau = $devoir === null;
            $devoir ??= new Devoir(['affectation_id' => $affectation->id, 'created_by' => $auteur->id, 'est_publie' => false]);
            $devoir->fill($donnees);
            $devoir->save();
            if ($sujet) {
                if ($devoir->fichier_chemin) {
                    Storage::disk('local')->delete($devoir->fichier_chemin);
                }
                $fichier = $this->stocker($sujet, "devoirs/{$devoir->id}/sujet");
                $devoir->update(['fichier_chemin' => $fichier['fichier_chemin'], 'fichier_nom' => $fichier['fichier_nom']]);
            }
            $this->journal->enregistrer($nouveau ? 'devoir.cree' : 'devoir.modifie', $devoir, "Devoir « {$devoir->titre} » ({$affectation->libelle()})");

            return $devoir;
        });
    }

    /**
     * Publie le devoir et prévient les élèves et leurs responsables disposant d'un compte (notification interne).
     */
    public function publierDevoir(Devoir $devoir): int
    {
        if ($devoir->est_publie) {
            return 0;
        }
        $devoir->update(['est_publie' => true, 'publie_at' => now()]);
        $this->journal->enregistrer('devoir.publie', $devoir, "Devoir « {$devoir->titre} » publié, à rendre le {$devoir->date_limite->format('d/m/Y H:i')}");

        $destinataires = Inscription::query()->with(['eleve.user', 'eleve.responsables.user'])
            ->where('classe_id', $devoir->affectation->classe_id)->where('statut', StatutInscription::Inscrit)->get()
            ->flatMap(fn ($i) => $i->eleve->responsables->pluck('user')->push($i->eleve->user))
            ->filter()->unique('id');
        foreach ($destinataires as $user) {
            $user->notify(new DevoirPublie($devoir));
        }

        return $destinataires->count();
    }

    public function supprimerDevoir(Devoir $devoir): void
    {
        if ($devoir->remises()->exists()) {
            throw ValidationException::withMessages(['devoir' => 'Des travaux ont été remis ou notés : ce devoir ne peut plus être supprimé.']);
        }
        $titre = $devoir->titre;
        $affectation = $devoir->affectation;
        $devoir->delete();
        Storage::disk('local')->deleteDirectory("devoirs/{$devoir->id}");
        $this->journal->enregistrer('devoir.supprime', $affectation, "Devoir « {$titre} » supprimé");
    }

    /**
     * Dépôt (ou remplacement) du travail d'un élève, jusqu'à la date limite (ou après si le retard est accepté).
     */
    public function remettre(Devoir $devoir, Inscription $inscription, ?UploadedFile $fichier, ?string $commentaire): RemiseDevoir
    {
        if (! etablissement()->parametre('remise_devoirs_en_ligne', true) || ! $devoir->remise_en_ligne) {
            throw ValidationException::withMessages(['fichier' => 'Ce devoir se rend en classe, pas en ligne.']);
        }
        if (! $devoir->accepteLesRemises()) {
            throw ValidationException::withMessages(['fichier' => 'La date limite est dépassée : le dépôt n\'est plus possible.']);
        }
        if (! $fichier && blank($commentaire)) {
            throw ValidationException::withMessages(['fichier' => 'Joignez votre travail ou rédigez votre réponse.']);
        }

        return DB::transaction(function () use ($devoir, $inscription, $fichier, $commentaire) {
            $remise = RemiseDevoir::query()->where('devoir_id', $devoir->id)->where('inscription_id', $inscription->id)->lockForUpdate()->first();
            if ($remise?->estCorrigee()) {
                throw ValidationException::withMessages(['fichier' => 'Votre travail a déjà été corrigé : il ne peut plus être remplacé.']);
            }

            $donnees = ['commentaire' => $commentaire ?: null, 'remis_at' => now(), 'en_retard' => $devoir->estEchu()];
            if ($fichier) {
                if ($remise?->fichier_chemin) {
                    Storage::disk('local')->delete($remise->fichier_chemin);
                }
                $donnees += $this->stocker($fichier, "devoirs/{$devoir->id}/remises/{$inscription->id}");
            }

            return RemiseDevoir::updateOrCreate(['devoir_id' => $devoir->id, 'inscription_id' => $inscription->id], $donnees);
        });
    }

    /**
     * Note et commentaire de correction. Pour un devoir rendu en classe, la note crée la fiche de remise.
     */
    public function corriger(Devoir $devoir, Inscription $inscription, ?float $note, ?string $correction, User $correcteur): RemiseDevoir
    {
        if ($note !== null && ($note < 0 || $note > (float) $devoir->bareme)) {
            throw ValidationException::withMessages(['note' => 'La note doit être comprise entre 0 et '.note_fr($devoir->bareme).'.']);
        }
        if ($inscription->classe_id !== $devoir->affectation->classe_id) {
            throw ValidationException::withMessages(['note' => 'Cet élève n\'est pas dans la classe du devoir.']);
        }
        $remise = RemiseDevoir::query()->where('devoir_id', $devoir->id)->where('inscription_id', $inscription->id)->first();
        if (! $remise && $devoir->remise_en_ligne && etablissement()->parametre('remise_devoirs_en_ligne', true) && $note === null) {
            throw ValidationException::withMessages(['note' => 'Aucun travail remis par cet élève.']);
        }

        $avant = $remise ? ['note' => $remise->note, 'correction' => $remise->correction] : null;
        $remise = RemiseDevoir::updateOrCreate(['devoir_id' => $devoir->id, 'inscription_id' => $inscription->id], [
            'note' => $note,
            'correction' => $correction ?: null,
            'corrige_par' => $correcteur->id,
            'corrige_at' => now(),
        ] + ($remise ? [] : ['remis_at' => now(), 'en_retard' => false]));

        if ($avant && $avant['note'] !== null && (float) $avant['note'] !== $note) {
            $this->journal->enregistrer('devoir.note_modifiee', $remise, "Note de {$inscription->eleve->nom_complet} modifiée (« {$devoir->titre} »)",
                ['note' => (float) $avant['note']], ['note' => $note]);
        }

        return $remise;
    }

    /**
     * @return array{fichier_chemin: string, fichier_nom: string, fichier_mime: ?string, fichier_taille: int}
     */
    private function stocker(UploadedFile $fichier, string $dossier): array
    {
        return [
            'fichier_chemin' => $fichier->store($dossier, 'local'),
            'fichier_nom' => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'fichier_mime' => $fichier->getMimeType(),
            'fichier_taille' => $fichier->getSize(),
        ];
    }

    private function verifierAnnee(Affectation $affectation): void
    {
        if ($affectation->classe->anneeScolaire->estCloturee()) {
            throw ValidationException::withMessages(['affectation' => 'Cette année scolaire est clôturée.']);
        }
    }
}
