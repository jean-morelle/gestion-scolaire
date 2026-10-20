<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin</title>
    {{-- dompdf : CSS 2.1, tableaux plutôt que flex ; DejaVu Sans pour les accents. --}}
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table { border-collapse: collapse; width: 100%; }
        .entete td { vertical-align: top; }
        .etab { font-size: 12pt; font-weight: bold; }
        .petit { font-size: 7.5pt; color: #475569; }
        h1 { font-size: 13pt; text-align: center; margin: 10px 0 2px; letter-spacing: 1px; }
        .sous-titre { text-align: center; font-size: 9pt; margin-bottom: 8px; }
        .cadre td { border: 1px solid #94a3b8; padding: 4px 6px; }
        .notes th { background: #e2e8f0; border: 1px solid #94a3b8; padding: 4px; font-size: 7.5pt; text-transform: uppercase; }
        .notes td { border: 1px solid #94a3b8; padding: 3px 4px; }
        .num { text-align: right; }
        .centre { text-align: center; }
        .total td { background: #f1f5f9; font-weight: bold; }
        .resume td { border: 1px solid #94a3b8; padding: 5px 6px; vertical-align: top; }
        .grand { font-size: 13pt; font-weight: bold; }
        .signature td { padding-top: 6px; height: 60px; vertical-align: top; }
        .faible { color: #b91c1c; }
    </style>
</head>
<body>
@foreach ($bulletins as $bulletin)
    @php
        $eleve = $bulletin->inscription->eleve;
        $classe = $bulletin->inscription->classe;
        $f = fn ($v, $dec = null) => $v === null ? '—' : number_format((float) $v, $dec ?? $decimales, ',', ' ');
        $lignes = collect($bulletin->lignes);
        $coefs = $lignes->filter(fn ($l) => $l['moyenne'] !== null)->sum('coefficient');
    @endphp
    <div class="page">
        <table class="entete">
            <tr>
                <td style="width: 60%">
                    @if ($logo)<img src="{{ $logo }}" style="height: 48px; float: left; margin-right: 8px;" alt="">@endif
                    <div class="etab">{{ $etablissement->nom }}</div>
                    @if ($etablissement->slogan)<div class="petit">{{ $etablissement->slogan }}</div>@endif
                    <div class="petit">{{ collect([$etablissement->adresse, $etablissement->ville])->filter()->join(', ') }}</div>
                    <div class="petit">{{ collect([$etablissement->telephone, $etablissement->email])->filter()->join(' · ') }}</div>
                </td>
                <td style="text-align: right">
                    <div><strong>Année scolaire {{ $bulletin->periode->anneeScolaire->libelle }}</strong></div>
                    <div class="petit">Publié le {{ $bulletin->publication?->publiee_at?->format('d/m/Y') }}@if ($bulletin->version > 1) · version {{ $bulletin->version }}@endif</div>
                    @unless ($bulletin->estVisibleDesFamilles())<div class="faible"><strong>Version retirée pour correction</strong></div>@endunless
                </td>
            </tr>
        </table>

        <h1>BULLETIN DE NOTES</h1>
        <div class="sous-titre">{{ $bulletin->periode->libelle }}</div>

        <table class="cadre">
            <tr>
                <td style="width: 50%"><strong>{{ $eleve->nom_complet }}</strong><br>
                    <span class="petit">Matricule {{ $eleve->matricule }}{{ $eleve->matricule_national ? ' · N° national '.$eleve->matricule_national : '' }}</span></td>
                <td>Né(e) le {{ $eleve->date_naissance->format('d/m/Y') }}{{ $eleve->lieu_naissance ? ' à '.$eleve->lieu_naissance : '' }}<br>
                    <span class="petit">Sexe : {{ $eleve->sexe->libelle() }}{{ $bulletin->inscription->redoublant ? ' · Redoublant(e)' : '' }}</span></td>
            </tr>
            <tr>
                <td>Classe : <strong>{{ $classe->nom }}</strong> · Effectif : {{ $bulletin->effectif }}</td>
                <td>Professeur principal : {{ $classe->professeurPrincipal?->user->name ?? '—' }}</td>
            </tr>
        </table>

        <table class="notes" style="margin-top: 8px">
            <thead>
                <tr>
                    <th style="text-align: left">Matière</th><th>Moy. /20</th><th>Coef.</th><th>Points</th><th>Rang</th>
                    <th>Moy. classe</th><th>Plus faible</th><th>Plus forte</th><th style="text-align: left">Enseignant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lignes as $l)
                    <tr>
                        <td>{{ $l['matiere'] }}</td>
                        <td class="num {{ $l['moyenne'] !== null && $l['moyenne'] < 10 ? 'faible' : '' }}"><strong>{{ $f($l['moyenne']) }}</strong></td>
                        <td class="centre">{{ $f($l['coefficient'], 1) }}</td>
                        <td class="num">{{ $f($l['points']) }}</td>
                        <td class="centre">{{ $l['rang'] ?? '—' }}</td>
                        <td class="num">{{ $f($l['moyenne_classe']) }}</td>
                        <td class="num">{{ $f($l['min']) }}</td>
                        <td class="num">{{ $f($l['max']) }}</td>
                        <td class="petit">{{ $l['enseignant'] ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td><td></td><td class="centre">{{ $f($coefs, 1) }}</td>
                    <td class="num">{{ $f($lignes->sum(fn ($l) => $l['points'] ?? 0)) }}</td><td colspan="5"></td>
                </tr>
            </tbody>
        </table>

        <table class="resume" style="margin-top: 8px">
            <tr>
                <td style="width: 34%">
                    Moyenne générale<br><span class="grand {{ $bulletin->moyenne_generale !== null && $bulletin->moyenne_generale < 10 ? 'faible' : '' }}">{{ $f($bulletin->moyenne_generale) }} / 20</span><br>
                    Rang : <strong>{{ $bulletin->rang ? $bulletin->rang.($bulletin->rang === 1 ? 'er' : 'e') : '—' }}</strong> sur {{ $bulletin->effectif }}
                </td>
                <td style="width: 33%">
                    Mention : <strong>{{ $bulletin->mention ?? '—' }}</strong><br>
                    Décision du conseil : <strong>{{ $bulletin->distinction ?? '—' }}</strong>
                    @if ($bulletin->moyenne_annuelle !== null)
                        <br><br>Moyenne annuelle : <strong>{{ $f($bulletin->moyenne_annuelle) }} / 20</strong>
                        @if ($bulletin->rang_annuel) · rang {{ $bulletin->rang_annuel }}@endif
                    @endif
                </td>
                <td>
                    Assiduité (demi-journées)<br>
                    Absences : <strong>{{ $bulletin->assiduite['absences'] ?? 0 }}</strong> dont non justifiées : <strong>{{ $bulletin->assiduite['non_justifiees'] ?? 0 }}</strong><br>
                    Retards : <strong>{{ $bulletin->assiduite['retards'] ?? 0 }}</strong>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="height: 46px"><strong>Appréciation générale :</strong> {{ $bulletin->appreciation ?? '' }}</td>
            </tr>
        </table>

        <table class="signature" style="margin-top: 10px">
            <tr>
                <td style="width: 50%">Le professeur principal</td>
                <td style="text-align: right">{{ $etablissement->chef_etablissement ? 'Le chef d\'établissement, '.$etablissement->chef_etablissement : 'Le chef d\'établissement' }}</td>
            </tr>
        </table>
        <div class="petit" style="margin-top: 4px">Moyennes sur 20. Document généré par {{ config('app.name') }} le {{ now()->format('d/m/Y') }}. Il ne peut être modifié qu'avec une nouvelle version publiée.</div>
    </div>
@endforeach
</body>
</html>
