@props(['couleur' => 'gris'])
@php
    $classes = [
        'gris' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'bleu' => 'bg-marque-50 text-marque-700 ring-marque-200',
        'vert' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'orange' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'rouge' => 'bg-red-50 text-red-700 ring-red-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
    ][$couleur] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>{{ $slot }}</span>
