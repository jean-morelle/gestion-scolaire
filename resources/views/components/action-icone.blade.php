{{-- Bouton d'action compact d'une ligne de tableau : icône + libellé accessible (lecteurs d'écran et info-bulle). --}}
@props(['href', 'icone', 'libelle', 'couleur' => 'marque'])
@php
    $teinte = ['marque' => 'text-marque-600 hover:bg-marque-50', 'gris' => 'text-slate-500 hover:bg-slate-100', 'rouge' => 'text-red-600 hover:bg-red-50'][$couleur] ?? 'text-marque-600 hover:bg-marque-50';
@endphp
<a href="{{ $href }}" title="{{ $libelle }}" {{ $attributes->merge(['class' => "inline-grid size-8 place-items-center rounded-lg transition $teinte focus-visible:outline-2 focus-visible:outline-marque-600"]) }}>
    <x-icone :nom="$icone" class="size-4" />
    <span class="sr-only">{{ $libelle }}</span>
</a>
