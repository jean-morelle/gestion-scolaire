<?php

namespace App\Http\Requests\Scolarite;

use App\Enums\LienParente;
use App\Models\Responsable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout ou modification d'un responsable légal. Pour l'ajout, « mode » vaut « nouveau »
 * (fiche à créer) ou « existant » (recherche par téléphone, cas des fratries).
 */
class ResponsableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('eleves.gerer');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::nettoyer($this->all()));
    }

    public function rules(): array
    {
        $modification = $this->route('responsable') !== null;

        return array_merge(
            $modification ? self::reglesFiche() : self::reglesAjout(),
            self::reglesLien(),
        );
    }

    public function attributes(): array
    {
        return self::attributs();
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglesAjout(string $prefixe = ''): array
    {
        $fiche = collect(self::reglesFiche())
            ->mapWithKeys(fn ($regles, $champ) => [$prefixe.$champ => array_map(
                fn ($r) => $r === 'required' ? "required_if:{$prefixe}mode,nouveau" : $r,
                array_merge(['nullable'], array_diff($regles, ['nullable']))
            )])
            ->all();

        return array_merge([
            $prefixe.'mode' => ['required', 'in:nouveau,existant'],
            $prefixe.'telephone_existant' => ["required_if:{$prefixe}mode,existant", 'nullable', 'string', 'max:30',
                Rule::exists('responsables', 'telephone')],
        ], $fiche);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function reglesFiche(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'regex:/^\+?[0-9]{6,15}$/'],
            'telephone_2' => ['nullable', 'string', 'regex:/^\+?[0-9]{6,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function reglesLien(): array
    {
        return [
            'lien' => ['required', Rule::enum(LienParente::class)],
            'est_contact_principal' => ['boolean'],
            'est_payeur' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributs(string $prefixe = ''): array
    {
        return [
            $prefixe.'nom' => 'nom du responsable',
            $prefixe.'prenoms' => 'prénoms du responsable',
            $prefixe.'telephone' => 'téléphone du responsable',
            $prefixe.'telephone_2' => 'second téléphone',
            $prefixe.'telephone_existant' => 'téléphone du responsable existant',
            'lien' => 'lien de parenté',
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    public static function nettoyer(array $donnees): array
    {
        foreach (['telephone', 'telephone_2', 'telephone_existant'] as $champ) {
            if (isset($donnees[$champ]) && is_string($donnees[$champ])) {
                $donnees[$champ] = preg_replace('/\s+/', '', $donnees[$champ]) ?: null;
            }
        }

        return $donnees;
    }

    /**
     * Fiche à créer, ou ['id' => …] du responsable existant retrouvé par son téléphone.
     *
     * @param  array<string, mixed>  $valides
     * @return array<string, mixed>
     */
    public static function responsable(array $valides): array
    {
        if (($valides['mode'] ?? 'nouveau') === 'existant') {
            return ['id' => Responsable::query()->where('telephone', $valides['telephone_existant'])->orderBy('id')->value('id')];
        }

        return array_intersect_key($valides, self::reglesFiche());
    }

    public function messages(): array
    {
        return self::messagesCommuns();
    }

    /**
     * @return array<string, string>
     */
    public static function messagesCommuns(string $prefixe = ''): array
    {
        return [
            $prefixe.'telephone.regex' => 'Le téléphone ne doit contenir que des chiffres (et éventuellement +).',
            $prefixe.'telephone_2.regex' => 'Le second téléphone ne doit contenir que des chiffres (et éventuellement +).',
            $prefixe.'telephone_existant.exists' => 'Aucun responsable enregistré n\'a ce numéro de téléphone.',
            $prefixe.'nom.required_if' => 'Le nom du responsable est obligatoire.',
            $prefixe.'prenoms.required_if' => 'Les prénoms du responsable sont obligatoires.',
            $prefixe.'telephone.required_if' => 'Le téléphone du responsable est obligatoire (RG-ELV-05).',
            $prefixe.'telephone_existant.required_if' => 'Indiquez le téléphone du responsable déjà enregistré.',
        ];
    }
}
