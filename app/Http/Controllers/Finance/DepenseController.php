<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ModePaiement;
use App\Enums\StatutDepense;
use App\Http\Controllers\Controller;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Services\DepenseService;
use App\Services\JournalService;
use App\Support\ExportCsv;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DepenseController extends Controller
{
    public function __construct(private DepenseService $service) {}

    public function index(Request $request): View|Response
    {
        $du = $this->date($request->input('du')) ?? CarbonImmutable::today()->startOfMonth();
        $au = $this->date($request->input('au')) ?? CarbonImmutable::today();

        $requete = Depense::query()->with(['categorie', 'auteur'])
            ->whereDate('date_depense', '>=', $du)->whereDate('date_depense', '<=', $au)
            ->when($request->filled('categorie'), fn (Builder $q) => $q->where('categorie_depense_id', $request->integer('categorie')))
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('statut', $request->input('statut')))
            ->when($request->filled('q'), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('libelle', 'like', '%'.$request->string('q').'%')
                ->orWhere('numero', 'like', '%'.$request->string('q').'%')->orWhere('beneficiaire', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('date_depense')->orderByDesc('id');

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger("depenses-{$du->toDateString()}-{$au->toDateString()}.csv",
                ['Numéro', 'Date', 'Catégorie', 'Libellé', 'Bénéficiaire', 'Montant', 'Mode', 'Pièce', 'Saisi par', 'Statut', 'Motif d\'annulation'],
                $requete->lazy()->map(fn (Depense $d) => [$d->numero, $d->date_depense, $d->categorie->libelle, $d->libelle, $d->beneficiaire,
                    $d->montant, $d->mode, $d->reference_piece, $d->auteur->name, $d->statut, $d->motif_annulation]));
        }

        $valides = (clone $requete)->reorder()->setEagerLoads([])->where('statut', StatutDepense::Valide);

        return view('finance.depenses.index', [
            'depenses' => $requete->paginate(25)->withQueryString(),
            'du' => $du, 'au' => $au,
            'total' => (int) (clone $valides)->sum('montant'),
            'parCategorie' => (clone $valides)->join('categories_depenses', 'categories_depenses.id', '=', 'depenses.categorie_depense_id')
                ->selectRaw('categories_depenses.libelle, SUM(depenses.montant) as total')->groupBy('categories_depenses.libelle')->orderByDesc('total')->get(),
            'categories' => CategorieDepense::query()->withCount('depenses')->orderBy('libelle')->get(),
        ]);
    }

    public function create(): View
    {
        return view('finance.depenses.create', [
            'categories' => CategorieDepense::query()->orderBy('libelle')->pluck('libelle', 'id'),
            'tailleMaxKo' => (int) etablissement()->parametre('taille_max_fichier_ko', 5120),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tailleMax = (int) etablissement()->parametre('taille_max_fichier_ko', 5120);
        $request->merge(['montant' => preg_replace('/\D/', '', (string) $request->input('montant'))]);
        $donnees = $request->validate([
            'categorie_depense_id' => ['required', 'exists:categories_depenses,id'],
            'libelle' => ['required', 'string', 'max:150'],
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'date_depense' => ['required', 'date'],
            'mode' => ['required', Rule::enum(ModePaiement::class)],
            'beneficiaire' => ['nullable', 'string', 'max:150'],
            'reference_piece' => ['nullable', 'string', 'max:100'],
            'justificatif' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$tailleMax}"],
        ], [], ['categorie_depense_id' => 'catégorie', 'libelle' => 'libellé', 'date_depense' => 'date', 'reference_piece' => 'référence de la pièce']);

        try {
            $depense = $this->service->enregistrer(collect($donnees)->except('justificatif')->all(), $request->file('justificatif'), $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('depenses.index')->with('succes', "Dépense {$depense->numero} enregistrée (".montant($depense->montant).').');
    }

    public function annuler(Request $request, Depense $depense): RedirectResponse
    {
        $donnees = $request->validate(['motif_annulation' => ['required', 'string', 'max:255']], [], ['motif_annulation' => 'motif']);

        try {
            $this->service->annuler($depense, $donnees['motif_annulation'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', "La dépense {$depense->numero} est annulée.");
    }

    public function justificatif(Depense $depense): Response
    {
        abort_unless($depense->justificatif_chemin && Storage::disk('local')->exists($depense->justificatif_chemin), 404);

        return Storage::disk('local')->download($depense->justificatif_chemin, $depense->numero.'.'.pathinfo($depense->justificatif_chemin, PATHINFO_EXTENSION));
    }

    public function storeCategorie(Request $request, JournalService $journal): RedirectResponse
    {
        $donnees = $request->validate(['libelle' => ['required', 'string', 'max:100', 'unique:categories_depenses,libelle']], [], ['libelle' => 'catégorie']);
        $categorie = CategorieDepense::create($donnees);
        $journal->enregistrer('categorie_depense.creee', null, "Catégorie de dépense « {$categorie->libelle} » créée");

        return back()->with('succes', "Catégorie « {$categorie->libelle} » ajoutée.");
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        return is_string($valeur) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) ? CarbonImmutable::parse($valeur) : null;
    }
}
