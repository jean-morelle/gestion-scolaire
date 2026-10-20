<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FraisEleve;
use App\Models\Remise;
use App\Services\RemiseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RemiseController extends Controller
{
    public function __construct(private RemiseService $service) {}

    public function store(Request $request, FraisEleve $frais): RedirectResponse
    {
        $request->merge(['montant' => preg_replace('/\D/', '', (string) $request->input('montant'))]);
        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'motif' => ['required', 'string', 'max:255'],
        ], [], ['motif' => 'motif de la remise']);

        try {
            $this->service->accorder($frais, (int) $donnees['montant'], $donnees['motif'], $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('succes', 'Remise de '.montant((int) $donnees['montant'])." accordée sur « {$frais->libelle} ».");
    }

    public function annuler(Request $request, Remise $remise): RedirectResponse
    {
        $donnees = $request->validate(['motif_annulation' => ['required', 'string', 'max:255']], [], ['motif_annulation' => 'motif']);

        try {
            $this->service->annuler($remise, $donnees['motif_annulation'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', 'Remise annulée.');
    }
}
