<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $paiement->numero_recu }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; }
        table { border-collapse: collapse; width: 100%; }
        .etab { font-size: 12pt; font-weight: bold; }
        .petit { font-size: 7.5pt; color: #475569; }
        .titre { font-size: 14pt; font-weight: bold; letter-spacing: 1px; }
        .cadre td { border: 1px solid #94a3b8; padding: 4px 6px; }
        .detail th { background: #e2e8f0; border: 1px solid #94a3b8; padding: 3px; font-size: 7.5pt; text-transform: uppercase; text-align: left; }
        .detail td { border: 1px solid #94a3b8; padding: 3px 5px; }
        .num { text-align: right; }
        .montant { font-size: 15pt; font-weight: bold; }
        .annule { position: absolute; top: 38%; left: 18%; font-size: 64pt; color: #dc2626; opacity: 0.25; transform: rotate(-20deg); font-weight: bold; letter-spacing: 6px; }
    </style>
</head>
<body>
    @if ($paiement->estAnnule())<div class="annule">ANNULÉ</div>@endif
    <table>
        <tr>
            <td style="width: 60%; vertical-align: top">
                <div class="etab">{{ $etablissement->nom }}</div>
                <div class="petit">{{ collect([$etablissement->adresse, $etablissement->ville, $etablissement->telephone])->filter()->join(' · ') }}</div>
            </td>
            <td style="text-align: right; vertical-align: top">
                <div class="titre">REÇU DE PAIEMENT</div>
                <div><strong>N° {{ $paiement->numero_recu }}</strong></div>
                <div class="petit">Le {{ $paiement->date_paiement->format('d/m/Y') }} · année {{ $paiement->inscription->anneeScolaire->libelle }}</div>
            </td>
        </tr>
    </table>

    <table class="cadre" style="margin-top: 8px">
        <tr>
            <td style="width: 55%">Élève : <strong>{{ $paiement->inscription->eleve->nom_complet }}</strong><br>
                <span class="petit">Matricule {{ $paiement->inscription->eleve->matricule }} · Classe {{ $paiement->inscription->classe->nom }}</span></td>
            <td>Montant reçu<br><span class="montant">{{ montant($paiement->montant) }}</span></td>
        </tr>
        <tr>
            <td>Payé par : {{ $paiement->payeur_nom ?? '—' }}</td>
            <td>Mode : {{ $paiement->mode->libelle() }}{{ $paiement->reference_externe ? ' · réf. '.$paiement->reference_externe : '' }}</td>
        </tr>
    </table>

    <table class="detail" style="margin-top: 8px">
        <tr><th>Affecté à</th><th>Échéance</th><th class="num">Montant</th></tr>
        @foreach ($paiement->imputations as $imputation)
            <tr>
                <td>{{ $imputation->fraisEleve->libelle }}</td>
                <td>{{ $imputation->fraisEleve->date_limite->format('d/m/Y') }}</td>
                <td class="num">{{ montant($imputation->montant) }}</td>
            </tr>
        @endforeach
    </table>

    <table style="margin-top: 8px">
        <tr>
            <td style="width: 55%; vertical-align: top">
                @if ($paiement->estAnnule())
                    <strong style="color: #b91c1c">Paiement annulé le {{ $paiement->annule_at->format('d/m/Y') }}</strong><br>
                    <span class="petit">Motif : {{ $paiement->motif_annulation }} · par {{ $paiement->annulateur?->name }}</span>
                @else
                    Reste à payer à ce jour : <strong>{{ montant(max(0, $solde)) }}</strong>
                @endif
            </td>
            <td style="text-align: right; vertical-align: top">
                Encaissé par {{ $paiement->encaisseur->name }}<br><br>
                <span class="petit">Signature et cachet</span>
            </td>
        </tr>
    </table>
    <p class="petit" style="margin-top: 14px">Reçu généré par {{ config('app.name') }} le {{ now()->format('d/m/Y à H:i') }}. Un reçu ne peut être ni modifié ni supprimé ; toute correction passe par une annulation tracée.</p>
</body>
</html>
