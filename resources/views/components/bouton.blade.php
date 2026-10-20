@props(['href' => null, 'variante' => 'primaire', 'icone' => null, 'type' => 'submit', 'petit' => false])
@php
    $classes = [
        'primaire' => 'bg-marque-600 text-white hover:bg-marque-700 focus-visible:outline-marque-600',
        'secondaire' => 'bg-white text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
        'succes' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:outline-emerald-600',
        'discret' => 'text-slate-600 hover:bg-slate-100',
    ][$variante];
    $taille = $petit ? 'px-2.5 py-1.5 text-xs' : 'px-3.5 py-2 text-sm';
    $base = "inline-flex items-center justify-center gap-1.5 rounded-lg font-medium shadow-xs transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 {$taille} {$classes}";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base]) }}>
        @if ($icone)<x-icone :nom="$icone" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base]) }}>
        @if ($icone)<x-icone :nom="$icone" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
