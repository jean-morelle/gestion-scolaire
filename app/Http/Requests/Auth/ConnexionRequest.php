<?php

namespace App\Http\Requests\Auth;

use App\Models\Eleve;
use App\Models\User;
use App\Services\JournalService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConnexionRequest extends FormRequest
{
    public const TENTATIVES_MAX = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifiant' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['identifiant' => 'identifiant', 'password' => 'mot de passe'];
    }

    /**
     * Connexion par e-mail ou par numéro de téléphone (décision Q7).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifiant = trim($this->string('identifiant'));
        // Les élèves, souvent sans e-mail ni téléphone, se connectent avec leur matricule (AAAA-NNNNN).
        $user = preg_match('/^\d{4}-\d{5}$/', $identifiant)
            ? Eleve::query()->where('matricule', $identifiant)->first()?->user
            : User::query()->where(str_contains($identifiant, '@') ? 'email' : 'telephone', preg_replace('/\s+/', '', $identifiant))->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);

            if (RateLimiter::attempts($this->throttleKey()) === self::TENTATIVES_MAX) {
                app(JournalService::class)->enregistrer(
                    'connexion.echecs_repetes', $user, "Échecs de connexion répétés pour « {$identifiant} »", userId: null,
                );
            }

            throw ValidationException::withMessages([
                'identifiant' => 'Identifiant ou mot de passe incorrect.',
            ]);
        }

        if (! $user->est_actif) {
            throw ValidationException::withMessages([
                'identifiant' => 'Ce compte est désactivé. Contactez l\'administration de l\'établissement.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::TENTATIVES_MAX)) {
            return;
        }

        event(new Lockout($this));

        $secondes = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifiant' => "Trop de tentatives de connexion. Réessayez dans {$secondes} secondes.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim($this->string('identifiant'))).'|'.$this->ip());
    }
}
