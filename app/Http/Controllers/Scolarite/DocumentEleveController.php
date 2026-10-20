<?php

namespace App\Http\Controllers\Scolarite;

use App\Http\Controllers\Controller;
use App\Models\DocumentEleve;
use App\Models\Eleve;
use App\Services\EleveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pièces du dossier scolaire : stockage privé, téléchargement uniquement après autorisation.
 */
class DocumentEleveController extends Controller
{
    public function __construct(private EleveService $eleves) {}

    public function store(Request $request, Eleve $eleve): RedirectResponse
    {
        $this->authorize('update', $eleve);
        $tailleMax = (int) etablissement()->parametre('taille_max_fichier_ko', 5120);

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:100'],
            'fichier' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$tailleMax}"],
        ], [], ['libelle' => 'intitulé de la pièce', 'fichier' => 'fichier']);

        $this->eleves->ajouterDocument($eleve, $donnees['libelle'], $request->file('fichier'));

        return back()->with('succes', "La pièce « {$donnees['libelle']} » a été ajoutée au dossier.");
    }

    public function show(Eleve $eleve, DocumentEleve $document): Response
    {
        $this->authorize('voirDossier', $eleve);
        abort_unless(Storage::disk('local')->exists($document->fichier_chemin), 404);

        return Storage::disk('local')->download($document->fichier_chemin, $document->fichier_nom);
    }

    public function destroy(Eleve $eleve, DocumentEleve $document): RedirectResponse
    {
        $this->authorize('update', $eleve);
        $this->eleves->supprimerDocument($document);

        return back()->with('succes', 'La pièce a été retirée du dossier.');
    }
}
