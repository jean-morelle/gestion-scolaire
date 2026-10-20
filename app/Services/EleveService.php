<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutEleve;
use App\Models\DocumentEleve;
use App\Models\Eleve;
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Dossiers des élèves et de leurs responsables légaux (RG-ELV, RG-PAR-01/04).
 * Les fichiers (photos, pièces) sont stockés sur le disque privé « local » et servis après autorisation.
 */
class EleveService
{
    public function __construct(
        private JournalService $journal,
        private NumerotationService $numerotation,
        private CompteService $comptes,
    ) {}

    /**
     * Crée l'élève avec son matricule (RG-ELV-01) et son premier responsable (RG-ELV-05).
     *
     * @param  array<string, mixed>  $responsable  champs d'un nouveau responsable, ou ['id' => …] pour un responsable existant
     * @param  array{lien: string, est_contact_principal?: bool, est_payeur?: bool}  $pivot
     */
    public function creer(array $donnees, array $responsable, array $pivot): Eleve
    {
        return DB::transaction(function () use ($donnees, $responsable, $pivot) {
            $anneeEntree = annee_active()?->date_debut->year ?? (int) now()->year;

            $eleve = Eleve::create(array_merge($this->normaliser($donnees), [
                'matricule' => $this->numerotation->matricule($anneeEntree),
                'statut' => StatutEleve::Actif,
            ]));

            $this->rattacher($eleve, $responsable, array_merge(['est_contact_principal' => true, 'est_payeur' => true], $pivot));
            $this->journal->enregistrer('eleve.cree', $eleve, "Dossier de {$eleve->nom_complet} créé ({$eleve->matricule})");

            return $eleve;
        });
    }

    public function modifier(Eleve $eleve, array $donnees): void
    {
        $avant = $eleve->only(array_keys($donnees));
        $eleve->update($this->normaliser($donnees));
        $apres = $eleve->only(array_keys($donnees));

        if ($avant != $apres) {
            $this->journal->enregistrer('eleve.modifie', $eleve, "Dossier de {$eleve->nom_complet} modifié",
                $this->serialiser($avant), $this->serialiser($apres));
        }
    }

    public function changerStatut(Eleve $eleve, StatutEleve $statut): void
    {
        $eleve->update(['statut' => $statut]);
        $this->journal->enregistrer($statut === StatutEleve::Archive ? 'eleve.archive' : 'eleve.desarchive', $eleve,
            "Dossier de {$eleve->nom_complet} : {$statut->libelle()}");
    }

    /**
     * RG-ELV-06 : suppression physique uniquement pour un dossier sans aucune inscription.
     */
    public function supprimer(Eleve $eleve): void
    {
        if ($eleve->inscriptions()->exists()) {
            throw ValidationException::withMessages(['eleve' => 'Un élève qui a déjà été inscrit ne peut pas être supprimé : archivez son dossier.']);
        }

        DB::transaction(function () use ($eleve) {
            $libelle = "{$eleve->nom_complet} ({$eleve->matricule})";
            $eleve->documents()->delete();
            $eleve->responsables()->detach();
            $eleve->delete();
            Storage::disk('local')->deleteDirectory("eleves/{$eleve->id}");
            $this->journal->enregistrer('eleve.supprime', null, "Dossier de {$libelle} supprimé");
        });
    }

    public function enregistrerPhoto(Eleve $eleve, UploadedFile $fichier): void
    {
        $ancienne = $eleve->photo_chemin;
        $chemin = $fichier->store("eleves/{$eleve->id}", 'local');
        $eleve->update(['photo_chemin' => $chemin]);

        if ($ancienne && $ancienne !== $chemin) {
            Storage::disk('local')->delete($ancienne);
        }
    }

    /**
     * Rattache un responsable nouveau ou existant (RG-PAR-04, journalisé).
     *
     * @param  array<string, mixed>  $responsable
     * @param  array<string, mixed>  $pivot
     */
    public function rattacher(Eleve $eleve, array $responsable, array $pivot): Responsable
    {
        return DB::transaction(function () use ($eleve, $responsable, $pivot) {
            $fiche = isset($responsable['id'])
                ? Responsable::findOrFail($responsable['id'])
                : Responsable::create($this->normaliserResponsable($responsable));

            if ($eleve->responsables()->whereKey($fiche->id)->exists()) {
                throw ValidationException::withMessages(['responsable' => "{$fiche->nom_complet} est déjà responsable de cet élève."]);
            }

            if (! empty($pivot['est_contact_principal'])) {
                $this->retirerContactPrincipal($eleve);
            }

            $eleve->responsables()->attach($fiche->id, [
                'lien' => $pivot['lien'],
                'est_contact_principal' => (bool) ($pivot['est_contact_principal'] ?? false),
                'est_payeur' => (bool) ($pivot['est_payeur'] ?? false),
            ]);

            $this->journal->enregistrer('responsable.rattache', $eleve,
                "{$fiche->nom_complet} rattaché(e) à {$eleve->nom_complet}", null,
                ['responsable_id' => $fiche->id, 'lien' => $pivot['lien']]);

            return $fiche;
        });
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public function modifierResponsable(Responsable $responsable, array $donnees, ?Eleve $eleve = null, array $pivot = []): void
    {
        DB::transaction(function () use ($responsable, $donnees, $eleve, $pivot) {
            $responsable->update($this->normaliserResponsable($donnees));

            if ($eleve && $pivot !== []) {
                if (! empty($pivot['est_contact_principal'])) {
                    $this->retirerContactPrincipal($eleve);
                }
                $eleve->responsables()->updateExistingPivot($responsable->id, [
                    'lien' => $pivot['lien'],
                    'est_contact_principal' => (bool) ($pivot['est_contact_principal'] ?? false),
                    'est_payeur' => (bool) ($pivot['est_payeur'] ?? false),
                ]);
            }

            $this->journal->enregistrer('responsable.modifie', $responsable, "Fiche de {$responsable->nom_complet} modifiée");
        });
    }

    /**
     * RG-ELV-05 : le dernier responsable d'un élève ne peut pas être détaché.
     */
    public function detacher(Eleve $eleve, Responsable $responsable): void
    {
        if ($eleve->responsables()->count() <= 1) {
            throw ValidationException::withMessages(['responsable' => 'Un élève doit toujours avoir au moins un responsable légal.']);
        }

        $eleve->responsables()->detach($responsable->id);
        $this->journal->enregistrer('responsable.detache', $eleve,
            "{$responsable->nom_complet} détaché(e) de {$eleve->nom_complet}", ['responsable_id' => $responsable->id]);
    }

    /**
     * Crée le compte parent d'un responsable (RG-PAR-01/05).
     *
     * @return string le mot de passe temporaire, à communiquer une seule fois
     */
    public function creerCompteParent(Responsable $responsable): string
    {
        if ($responsable->user_id) {
            throw ValidationException::withMessages(['compte' => 'Ce responsable a déjà un compte.']);
        }

        $email = $responsable->email ? mb_strtolower($responsable->email) : null;
        $telephone = $responsable->telephone;
        $existant = User::query()
            ->where('telephone', $telephone)
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->first();
        if ($existant) {
            throw ValidationException::withMessages(['compte' => "Un compte utilise déjà ce téléphone ou cet e-mail ({$existant->name})."]);
        }

        return DB::transaction(function () use ($responsable, $email, $telephone) {
            [$user, $motDePasse] = $this->comptes->creer($responsable->nom_complet, $email, $telephone, [Role::Parent->value]);
            $responsable->update(['user_id' => $user->id]);
            $this->journal->enregistrer('responsable.compte_cree', $responsable, "Compte parent créé pour {$responsable->nom_complet}");

            return $motDePasse;
        });
    }

    /**
     * Compte de l'espace élève : identifiant = matricule, mot de passe temporaire à changer à la première connexion.
     *
     * @return string le mot de passe temporaire
     */
    public function creerCompteEleve(Eleve $eleve): string
    {
        if ($eleve->user_id) {
            throw ValidationException::withMessages(['compte' => 'Cet élève a déjà un compte.']);
        }
        if ($eleve->statut === StatutEleve::Archive) {
            throw ValidationException::withMessages(['compte' => 'Ce dossier est archivé.']);
        }

        return DB::transaction(function () use ($eleve) {
            [$user, $motDePasse] = $this->comptes->creer($eleve->nom_complet, null, null, [Role::Eleve->value]);
            $eleve->update(['user_id' => $user->id]);
            $this->journal->enregistrer('eleve.compte_cree', $eleve, "Compte élève créé pour {$eleve->nom_complet} (identifiant {$eleve->matricule})");

            return $motDePasse;
        });
    }

    public function ajouterDocument(Eleve $eleve, string $libelle, UploadedFile $fichier): DocumentEleve
    {
        $chemin = $fichier->store("eleves/{$eleve->id}/documents", 'local');

        $document = $eleve->documents()->create([
            'libelle' => $libelle,
            'fichier_chemin' => $chemin,
            'fichier_nom' => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'fichier_mime' => $fichier->getMimeType(),
            'fichier_taille' => $fichier->getSize(),
            'ajoute_par' => auth()->id(),
        ]);
        $this->journal->enregistrer('eleve.document_ajoute', $eleve, "Pièce « {$libelle} » ajoutée au dossier de {$eleve->nom_complet}");

        return $document;
    }

    public function supprimerDocument(DocumentEleve $document): void
    {
        Storage::disk('local')->delete($document->fichier_chemin);
        $document->delete();
        $this->journal->enregistrer('eleve.document_supprime', $document->eleve,
            "Pièce « {$document->libelle} » retirée du dossier de {$document->eleve->nom_complet}");
    }

    private function retirerContactPrincipal(Eleve $eleve): void
    {
        DB::table('eleve_responsable')->where('eleve_id', $eleve->id)->update(['est_contact_principal' => false]);
    }

    private function normaliser(array $donnees): array
    {
        if (isset($donnees['nom'])) {
            $donnees['nom'] = mb_strtoupper(trim($donnees['nom']));
        }
        if (isset($donnees['prenoms'])) {
            $donnees['prenoms'] = mb_convert_case(trim($donnees['prenoms']), MB_CASE_TITLE);
        }

        return $donnees;
    }

    private function normaliserResponsable(array $donnees): array
    {
        $donnees = $this->normaliser($donnees);
        foreach (['telephone', 'telephone_2'] as $champ) {
            if (isset($donnees[$champ])) {
                $donnees[$champ] = preg_replace('/\s+/', '', $donnees[$champ]);
            }
        }
        if (! empty($donnees['email'])) {
            $donnees['email'] = mb_strtolower(trim($donnees['email']));
        }

        return array_intersect_key($donnees, array_flip((new Responsable)->getFillable()));
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
