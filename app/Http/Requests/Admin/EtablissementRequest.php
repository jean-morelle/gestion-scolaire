<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EtablissementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('etablissement.gerer');
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'sigle' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'devise' => ['required', 'string', 'max:10'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'chef_etablissement' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'image_connexion' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'parametres.decimales' => ['required', 'integer', 'between:0,3'],
            'parametres.absence_evaluation' => ['required', 'in:ignoree,zero'],
            'parametres.delai_modification_appel_heures' => ['required', 'integer', 'between:0,720'],
            'parametres.notifier_absences' => ['boolean'],
            'parametres.remise_devoirs_en_ligne' => ['boolean'],
            'parametres.taille_max_fichier_ko' => ['required', 'integer', 'between:100,20480'],
            'parametres.jours_rappel_echeance' => ['required', 'integer', 'between:0,60'],
            'parametres.mentions' => ['required', 'array', 'size:5'],
            'parametres.mentions.*.libelle' => ['required', 'string', 'max:50'],
            'parametres.mentions.*.min' => ['required', 'numeric', 'between:0,20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'parametres.decimales' => 'nombre de décimales',
            'parametres.delai_modification_appel_heures' => 'délai de modification des appels',
            'parametres.taille_max_fichier_ko' => 'taille maximale des fichiers',
            'parametres.jours_rappel_echeance' => 'délai de rappel des échéances',
            'parametres.mentions.*.libelle' => 'libellé de mention',
            'parametres.mentions.*.min' => 'seuil de mention',
        ];
    }
}
