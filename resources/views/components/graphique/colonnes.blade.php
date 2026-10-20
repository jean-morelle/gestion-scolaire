{{--
    Histogramme en colonnes, une seule série (une teinte, pas de légende : le titre de la carte la nomme).
    $points : liste de ['libelle' => string, 'valeur' => int|float, 'details' => [libellé => valeur]].
    Spécifications : colonnes ≤ 24 px, bout arrondi 4 px carré à la base, grille 1 px discrète, valeur affichée
    seulement sur la dernière colonne et le maximum ; info-bulle au survol et au clavier ; tableau des données.
--}}
@props(['points', 'unite' => '', 'description' => 'Graphique'])
@php
    $largeur = 640; $hauteur = 220; $gauche = 40; $droite = 12; $haut = 22; $bas = 26;
    $n = max(count($points), 1);
    $max = max(1, collect($points)->max('valeur'));
    // Graduation « propre » : 1, 2, 2,5 ou 5 × 10^k.
    $pas = (function ($m) { $brut = $m / 4; $p = 10 ** floor(log10($brut)); foreach ([1, 2, 2.5, 5, 10] as $f) { if ($f * $p >= $brut) { return $f * $p; } } return 10 * $p; })($max);
    $plafond = $pas * ceil($max / $pas);
    $zoneH = $hauteur - $haut - $bas;
    $fente = ($largeur - $gauche - $droite) / $n;
    $epaisseur = min(24, $fente * 0.55);
    $y = fn ($v) => $haut + $zoneH - ($v / $plafond) * $zoneH;
    $iMax = collect($points)->search(fn ($p) => $p['valeur'] == $max);
    $compact = fn ($v) => $v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1, ',', ''), '0'), ',').' k' : number_format($v, 0, ',', ' ');
@endphp
<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ i: null }" @mouseleave="i = null">
    <svg viewBox="0 0 {{ $largeur }} {{ $hauteur }}" class="h-auto w-full" role="img" aria-label="{{ $description }}">
        {{-- Grille horizontale et graduations --}}
        @for ($v = 0; $v <= $plafond + 0.001; $v += $pas)
            <line x1="{{ $gauche }}" x2="{{ $largeur - $droite }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="#e2e8f0" stroke-width="1" />
            <text x="{{ $gauche - 8 }}" y="{{ $y($v) + 4 }}" text-anchor="end" font-size="11" fill="#64748b">{{ $compact($v) }}</text>
        @endfor

        @foreach ($points as $k => $p)
            @php
                $cx = $gauche + $fente * $k + $fente / 2;
                $x0 = $cx - $epaisseur / 2;
                $yv = $y($p['valeur']);
                $h = $haut + $zoneH - $yv;
                $r = min(4, $h, $epaisseur / 2);
            @endphp
            @if ($h > 0)
                <path d="M{{ $x0 }},{{ $haut + $zoneH }} V{{ $yv + $r }} Q{{ $x0 }},{{ $yv }} {{ $x0 + $r }},{{ $yv }} H{{ $x0 + $epaisseur - $r }} Q{{ $x0 + $epaisseur }},{{ $yv }} {{ $x0 + $epaisseur }},{{ $yv + $r }} V{{ $haut + $zoneH }} Z"
                      :fill="i === {{ $k }} ? 'var(--color-marque-500)' : 'var(--color-marque-600)'" fill="var(--color-marque-600)" />
            @endif
            @if ($k === $n - 1 || $k === $iMax)
                <text x="{{ $cx }}" y="{{ $yv - 6 }}" text-anchor="middle" font-size="11" font-weight="600" fill="#0f172a">{{ $compact($p['valeur']) }}</text>
            @endif
            <text x="{{ $cx }}" y="{{ $hauteur - 8 }}" text-anchor="middle" font-size="11" fill="#64748b">{{ $p['libelle'] }}</text>
            {{-- Zone de survol : toute la hauteur de la tranche, plus large que la colonne. --}}
            <rect x="{{ $gauche + $fente * $k }}" y="{{ $haut }}" width="{{ $fente }}" height="{{ $zoneH }}" fill="transparent" tabindex="0" class="cursor-default focus:outline-none"
                  @mouseenter="i = {{ $k }}" @focus="i = {{ $k }}" @blur="i = null" aria-label="{{ $p['libelle'] }} : {{ $p['valeur'] }} {{ $unite }}" />
        @endforeach
        <line x1="{{ $gauche }}" x2="{{ $largeur - $droite }}" y1="{{ $haut + $zoneH }}" y2="{{ $haut + $zoneH }}" stroke="#cbd5e1" stroke-width="1" />
    </svg>

    {{-- Info-bulle : la valeur d'abord, le libellé ensuite. --}}
    @foreach ($points as $k => $p)
        <div x-cloak x-show="i === {{ $k }}" class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg"
             style="left: {{ round((($gauche + $fente * $k + $fente / 2) / $largeur) * 100, 2) }}%">
            <p class="text-sm font-semibold text-slate-900">{{ number_format($p['valeur'], 0, ',', ' ') }} {{ $unite }}</p>
            <p class="text-slate-500">{{ $p['libelle_long'] ?? $p['libelle'] }}</p>
            @foreach ($p['details'] ?? [] as $libelle => $valeur)
                <p class="text-slate-600">{{ $libelle }} : <span class="font-medium text-slate-800">{{ $valeur }}</span></p>
            @endforeach
        </div>
    @endforeach

    <details class="mt-2 text-xs text-slate-500">
        <summary class="cursor-pointer select-none hover:text-slate-700">Voir les données</summary>
        <table class="mt-2 w-full text-left">
            <thead><tr class="text-slate-500"><th class="py-1 font-medium">Période</th><th class="py-1 text-right font-medium">{{ ucfirst($unite) ?: 'Valeur' }}</th>
                @foreach (array_keys($points[0]['details'] ?? []) as $libelle)<th class="py-1 text-right font-medium">{{ $libelle }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @foreach ($points as $p)
                    <tr><td class="py-1">{{ $p['libelle_long'] ?? $p['libelle'] }}</td><td class="py-1 text-right">{{ number_format($p['valeur'], 0, ',', ' ') }}</td>
                        @foreach ($p['details'] ?? [] as $valeur)<td class="py-1 text-right">{{ $valeur }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    </details>
</div>
