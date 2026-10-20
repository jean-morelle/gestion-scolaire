<?php

namespace App\Http\Requests\Pedagogie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Profil enseignant : compte de connexion (nom, e-mail ou téléphone) et fiche professionnelle.
 */
class EnseignantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('enseignants.gerer');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'telephone' => $this->filled('telephone') ? preg_replace('/\s+/', '', $this->input('telephone')) : null,
            'matricule' => $this->filled('matricule') ? mb_strtoupper(trim($this->input('matricule'))) : null,
        ]);
    }

    public function rules(): array
    {
        $enseignant = $this->route('enseignant');
        $user = $enseignant?->user;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:telephone', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'telephone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9]{6,15}$/', Rule::unique('users')->ignore($user)],
            'matricule' => ['nullable', 'string', 'max:30', Rule::unique('enseignants')->ignore($enseignant)],
            'specialite' => ['nullable', 'string', 'max:100'],
            'diplome' => ['nullable', 'string', 'max:100'],
            'date_embauche' => ['nullable', 'date', 'before_or_equal:today'],
            'matieres' => ['array'],
            'matieres.*' => ['integer', 'exists:matieres,id'],
            'est_actif' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom complet', 'date_embauche' => 'date d\'embauche', 'diplome' => 'diplôme', 'specialite' => 'spécialité'];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Indiquez un e-mail ou un numéro de téléphone.',
            'telephone.required_without' => 'Indiquez un e-mail ou un numéro de téléphone.',
            'telephone.regex' => 'Le numéro de téléphone ne doit contenir que des chiffres (et éventuellement +).',
        ];
    }
}
