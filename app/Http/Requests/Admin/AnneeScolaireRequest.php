<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnneeScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('etablissement.gerer');
    }

    public function rules(): array
    {
        $annee = $this->route('annee');

        return [
            'libelle' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/', Rule::unique('annees_scolaires')->ignore($annee)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'decoupage' => [$annee ? 'nullable' : 'required', 'in:trimestres,semestres,aucun'],
        ];
    }

    public function messages(): array
    {
        return ['libelle.regex' => 'Le libellé doit avoir la forme 2026-2027.'];
    }
}
