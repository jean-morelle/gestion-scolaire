<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('utilisateurs.gerer');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'telephone' => $this->filled('telephone') ? preg_replace('/\s+/', '', $this->input('telephone')) : null,
        ]);
    }

    public function rules(): array
    {
        $utilisateur = $this->route('utilisateur');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:telephone', 'email', 'max:255', Rule::unique('users')->ignore($utilisateur)],
            'telephone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9]{6,15}$/', Rule::unique('users')->ignore($utilisateur)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(array_map(fn (Role $r) => $r->value, Role::personnel()))],
            'est_actif' => ['boolean'],
            'specialite' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom complet', 'roles' => 'rôles'];
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
