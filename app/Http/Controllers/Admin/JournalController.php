<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultation du journal d'audit. Aucune route de modification ni de suppression (RG-SEC-02).
 */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $entrees = JournalActivite::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('du'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('du')))
            ->when($request->filled('au'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('au')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $familles = JournalActivite::query()->selectRaw('DISTINCT action')->orderBy('action')->pluck('action')
            ->map(fn ($a) => explode('.', $a)[0])->unique()->values();

        return view('admin.journal', compact('entrees', 'familles'));
    }
}
