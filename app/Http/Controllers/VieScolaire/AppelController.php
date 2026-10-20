<?php

namespace App\Http\Controllers\VieScolaire;

use App\Enums\Creneau;
use App\Enums\StatutPresence;
use App\Http\Controllers\Controller;
use App\Models\Appel;
use App\Models\Classe;
use App\Services\AppelService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppelController extends Controller
{
    public function __construct(private AppelService $service) {}

    public function index(Request $request): View
    {
        [$date, $creneau] = $this->dateEtCreneau($request);
        $classes = $this->classesVisibles($request);

        $appels = Appel::query()->whereIn('classe_id', $classes->pluck('id'))->whereDate('date_appel', $date)->where('creneau', $creneau)
            ->with('auteur')->withCount([
                'presences as absents' => fn (Builder $q) => $q->whereIn('statut', [StatutPresence::Absent, StatutPresence::AbsentExcuse]),
                'presences as retards' => fn (Builder $q) => $q->where('statut', StatutPresence::Retard),
            ])->get()->keyBy('classe_id');

        $historique = Appel::query()->whereIn('classe_id', $classes->pluck('id'))
            ->with(['classe', 'auteur'])
            ->withCount(['presences as absents' => fn (Builder $q) => $q->whereIn('statut', [StatutPresence::Absent, StatutPresence::AbsentExcuse])])
            ->when($request->filled('classe'), fn (Builder $q) => $q->where('classe_id', $request->integer('classe')))
            ->orderByDesc('date_appel')->orderByDesc('creneau')
            ->paginate(15, ['*'], 'page')->withQueryString();

        return view('vie-scolaire.appels.index', [
            'date' => $date, 'creneau' => $creneau, 'classes' => $classes, 'appels' => $appels, 'historique' => $historique,
        ]);
    }

    public function saisie(Request $request, Classe $classe): View
    {
        [$date, $creneau] = $this->dateEtCreneau($request);
        $user = $request->user();
        abort_unless($this->service->peutFaireAppel($user, $classe) || $user->can('presences.consulter'), 403);

        $classe->load('anneeScolaire');
        $appel = Appel::query()->where('classe_id', $classe->id)->whereDate('date_appel', $date)->where('creneau', $creneau)
            ->with(['presences.justificatifs', 'auteur'])->first();

        $blocage = null;
        try {
            $this->service->verifierDate($classe, $date);
        } catch (ValidationException $e) {
            $blocage = collect($e->errors())->flatten()->first();
        }
        if (! $blocage && ! $this->service->peutFaireAppel($user, $classe)) {
            $blocage = 'Consultation seule : vous ne faites pas l\'appel de cette classe.';
        }
        if (! $blocage && $appel && ! $this->service->peutModifier($user, $appel)) {
            $blocage = 'Le délai de modification de cet appel est dépassé : adressez-vous à la vie scolaire.';
        }

        return view('vie-scolaire.appels.saisie', [
            'classe' => $classe,
            'date' => $date,
            'creneau' => $creneau,
            'appel' => $appel,
            'eleves' => $blocage && ! $appel ? collect() : $this->service->eleves($classe, $date),
            'presences' => $appel?->presences->keyBy('inscription_id') ?? collect(),
            'blocage' => $blocage,
        ]);
    }

    public function store(Request $request, Classe $classe): RedirectResponse
    {
        abort_unless($this->service->peutFaireAppel($request->user(), $classe), 403);

        $donnees = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'creneau' => ['required', Rule::enum(Creneau::class)],
            'lignes' => ['required', 'array'],
            'lignes.*.statut' => ['required', Rule::in([StatutPresence::Present->value, StatutPresence::Absent->value, StatutPresence::Retard->value])],
            'lignes.*.minutes_retard' => ['nullable', 'integer', 'between:1,240'],
            'lignes.*.remarque' => ['nullable', 'string', 'max:255'],
        ], [], ['lignes.*.minutes_retard' => 'minutes de retard', 'lignes.*.statut' => 'présence']);

        try {
            $appel = $this->service->enregistrer($classe, $donnees['date'], Creneau::from($donnees['creneau']), $donnees['lignes'], $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $absents = $appel->presences()->whereIn('statut', [StatutPresence::Absent, StatutPresence::AbsentExcuse])->count();

        return redirect()->route('appels.index', ['date' => $donnees['date'], 'creneau' => $donnees['creneau']])
            ->with('succes', "Appel de {$classe->nom} enregistré : {$absents} absent(s).");
    }

    /**
     * @return array{0: string, 1: Creneau}
     */
    private function dateEtCreneau(Request $request): array
    {
        $date = $request->input('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? CarbonImmutable::parse($date)->toDateString() : now()->toDateString();
        $creneau = Creneau::tryFrom((string) $request->input('creneau')) ?? (now()->hour < 13 ? Creneau::Matin : Creneau::ApresMidi);

        return [$date, $creneau];
    }

    /**
     * Toutes les classes de l'année active pour la vie scolaire ; celles où il intervient pour un enseignant.
     *
     * @return Collection<int, Classe>
     */
    private function classesVisibles(Request $request): Collection
    {
        $user = $request->user();
        $toutes = $user->can('presences.saisir') || $user->can('presences.consulter');
        $enseignant = $user->enseignant;

        return Classe::query()->deLAnneeActive()
            ->with('niveau')->withCount('inscriptionsActives')
            ->when(! $toutes, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('classes.professeur_principal_id', $enseignant?->id ?? 0)
                ->orWhereHas('affectations', fn (Builder $a) => $a->where('enseignant_id', $enseignant?->id ?? 0))))
            ->get();
    }
}
