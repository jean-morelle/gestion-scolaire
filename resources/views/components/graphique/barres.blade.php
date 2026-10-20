{{--
    Barres horizontales, une seule série (comparaison de grandeurs). Valeur au bout de chaque barre (peu de lignes),
    barres ≤ 24 px arrondies côté valeur, info-bulle au survol et au clavier.
    $lignes : liste de ['libelle' => string, 'valeur' => int, 'detail' => ?string].
--}}
@props(['lignes', 'unite' => '', 'description' => 'Graphique'])
@php
    $max = max(1, collect($lignes)->max('valeur'));
    $total = max(1, collect($lignes)->sum('valeur'));
@endphp
<div {{ $attributes->merge(['class' => 'space-y-2']) }} role="img" aria-label="{{ $description }}">
    @foreach ($lignes as $l)
        <div class="group grid grid-cols-[4.5rem_1fr_3.5rem] items-center gap-3 rounded-md px-1 py-0.5 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-marque-500"
             tabindex="0" title="{{ $l['libelle'] }} : {{ number_format($l['valeur'], 0, ',', ' ') }} {{ $unite }} ({{ number_format($l['valeur'] / $total * 100, 0) }} %){{ isset($l['detail']) ? ' · '.$l['detail'] : '' }}">
            <span class="truncate text-slate-600">{{ $l['libelle'] }}</span>
            <span class="relative block h-3.5">
                <span class="absolute inset-y-0 left-0 rounded-r bg-marque-600 transition-colors group-hover:bg-marque-500 group-focus:bg-marque-500"
                      style="width: {{ max(1.5, $l['valeur'] / $max * 100) }}%"></span>
            </span>
            <span class="text-right font-medium text-slate-800">{{ number_format($l['valeur'], 0, ',', ' ') }}</span>
        </div>
    @endforeach
</div>
