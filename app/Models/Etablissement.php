<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres de l'établissement. Une installation = un établissement = une seule ligne.
 */
class Etablissement extends Model
{
    protected $table = 'etablissement';

    protected $fillable = [
        'nom', 'sigle', 'adresse', 'ville', 'telephone', 'email', 'logo_chemin',
        'devise', 'slogan', 'chef_etablissement', 'parametres',
    ];

    protected function casts(): array
    {
        return [
            'parametres' => 'array',
        ];
    }

    public static function courant(): ?self
    {
        return static::query()->first();
    }

    /**
     * Lit un paramètre de fonctionnement (arrondis, mentions...) avec valeur par défaut.
     */
    public function parametre(string $cle, mixed $defaut = null): mixed
    {
        return data_get($this->parametres, $cle, $defaut);
    }
}
