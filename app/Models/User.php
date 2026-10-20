<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Enums\StatutInscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'telephone',
        'password',
        'est_actif',
        'doit_changer_mot_de_passe',
        'derniere_connexion_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
            'est_actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function enseignant(): HasOne
    {
        return $this->hasOne(Enseignant::class);
    }

    /**
     * Fiche responsable légal liée au compte parent.
     */
    public function responsable(): HasOne
    {
        return $this->hasOne(Responsable::class);
    }

    /**
     * Fiche élève liée au compte élève.
     */
    public function eleve(): HasOne
    {
        return $this->hasOne(Eleve::class);
    }

    public function aRole(Role $role): bool
    {
        return $this->hasRole($role->value);
    }

    /**
     * Membre du personnel de l'établissement (tout rôle autre que parent ou élève).
     */
    public function estPersonnel(): bool
    {
        return $this->hasAnyRole(array_map(fn (Role $r) => $r->value, Role::personnel()));
    }

    /**
     * Élèves dont l'utilisateur est le responsable légal (vide si ce n'est pas un parent).
     *
     * @return Collection<int, Eleve>
     */
    public function enfants(): Collection
    {
        if (! $this->aRole(Role::Parent) || ! $this->responsable) {
            return collect();
        }

        return $this->responsable->eleves()->orderBy('prenoms')->get();
    }

    /**
     * Identifiants des classes de l'année active concernant un parent ou un élève
     * (sert au ciblage des annonces).
     *
     * @return list<int>
     */
    public function classesSuivies(): array
    {
        $annee = AnneeScolaire::active();
        if (! $annee) {
            return [];
        }

        $eleveIds = $this->enfants()->pluck('id');
        if ($this->aRole(Role::Eleve) && $this->eleve) {
            $eleveIds->push($this->eleve->id);
        }

        return Inscription::query()
            ->whereIn('eleve_id', $eleveIds)
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut', StatutInscription::Inscrit)
            ->pluck('classe_id')
            ->all();
    }

    /**
     * Identifiant affiché (e-mail, sinon téléphone).
     */
    public function identifiant(): string
    {
        return $this->email ?? $this->telephone ?? $this->eleve?->matricule ?? '';
    }
}
