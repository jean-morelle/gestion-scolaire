<?php

namespace App\Http\Requests\Admin;

use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('etablissement.gerer');
    }

    public function rules(): array
    {
        /** @var AnneeScolaire $annee */
        $annee = $this->route('annee');
        $periode = $this->route('periode');

        return [
            'libelle' => ['required', 'string', 'max:50'],
            'ordre' => ['required', 'integer', 'between:1,12',
                Rule::unique('periodes')->where('annee_scolaire_id', $annee->id)->ignore($periode)],
            'date_debut' => ['required', 'date', 'after_or_equal:'.$annee->date_debut->format('Y-m-d')],
            'date_fin' => ['required', 'date', 'after:date_debut', 'before_or_equal:'.$annee->date_fin->format('Y-m-d')],
        ];
    }

    /**
     * RG-ANN-02 : les périodes d'une année ne se chevauchent pas.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $chevauchement = Periode::query()
                ->where('annee_scolaire_id', $this->route('annee')->id)
                ->when($this->route('periode'), fn ($q, $p) => $q->whereKeyNot($p->id))
                ->whereDate('date_debut', '<=', $this->date('date_fin'))
                ->whereDate('date_fin', '>=', $this->date('date_debut'))
                ->exists();
            if ($chevauchement) {
                $validator->errors()->add('date_debut', 'Ces dates chevauchent une autre période de l\'année.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'date_debut.after_or_equal' => 'La période doit commencer dans l\'année scolaire.',
            'date_fin.before_or_equal' => 'La période doit se terminer dans l\'année scolaire.',
            'ordre.unique' => 'Une autre période porte déjà ce numéro d\'ordre.',
        ];
    }
}
