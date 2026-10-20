<?php

namespace App\Http\Requests\Scolarite;

use App\Enums\LienParente;
use App\Enums\Sexe;
use App\Enums\StatutAnnee;
use App\Models\AnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Identité de l'élève. À la création : premier responsable légal (RG-ELV-05) et inscription facultative.
 */
class EleveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('eleves.gerer');
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('responsable'))) {
            $this->merge(['responsable' => ResponsableRequest::nettoyer($this->input('responsable'))]);
        }
        if ($this->filled('matricule_national')) {
            $this->merge(['matricule_national' => mb_strtoupper(trim($this->input('matricule_national')))]);
        }
    }

    public function rules(): array
    {
        $eleve = $this->route('eleve');

        $regles = [
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'lieu_naissance' => ['nullable', 'string', 'max:100'],
            'nationalite' => ['nullable', 'string', 'max:50'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'matricule_national' => ['nullable', 'string', 'max:30', Rule::unique('eleves')->ignore($eleve)],
            'observations' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        if ($eleve) {
            return $regles;
        }

        return array_merge($regles, ResponsableRequest::reglesAjout('responsable.'), [
            'responsable.lien' => ['required', Rule::enum(LienParente::class)],
            'classe_id' => ['nullable', Rule::exists('classes', 'id')->where(fn ($q) => $q->whereIn('annee_scolaire_id',
                AnneeScolaire::query()->where('statut', '!=', StatutAnnee::Cloturee->value)->select('id')))],
            'date_inscription' => ['nullable', 'required_with:classe_id', 'date'],
            'categorie_frais' => ['nullable', 'string', 'max:30'],
            'redoublant' => ['boolean'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(ResponsableRequest::attributs('responsable.'), [
            'prenoms' => 'prénoms',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
            'matricule_national' => 'matricule national',
            'responsable.lien' => 'lien de parenté',
            'classe_id' => 'classe',
            'date_inscription' => 'date d\'inscription',
        ]);
    }

    public function messages(): array
    {
        return array_merge(ResponsableRequest::messagesCommuns('responsable.'), [
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'classe_id.exists' => 'Cette classe n\'existe pas ou appartient à une année clôturée.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function identite(): array
    {
        return $this->safe()->only(['nom', 'prenoms', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite',
            'adresse', 'matricule_national', 'observations']);
    }
}
