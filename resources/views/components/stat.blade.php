@props(['libelle', 'valeur', 'detail' => null, 'icone' => null, 'couleur' => 'marque', 'href' => null])
@php
    $teintes = [
        'marque' => 'bg-marque-50 text-marque-700',
        'vert' => 'bg-emerald-50 text-emerald-700',
        'orange' => 'bg-amber-50 text-amber-700',
        'rouge' => 'bg-red-50 text-red-700',
        'violet' => 'bg-violet-50 text-violet-700',
    ][$couleur] ?? 'bg-marque-50 text-marque-700';
@endphp
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs'.($href ? ' transition hover:border-marque-300 hover:shadow-sm' : '')]) }}>
    @if ($icone)
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $teintes }}"><x-icone :nom="$icone" class="size-6" /></span>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-slate-500">{{ $libelle }}</p>
        <p class="mt-0.5 text-2xl font-semibold tracking-tight text-slate-900">{{ $valeur }}</p>
        @if ($detail)
            <p class="mt-0.5 text-xs text-slate-500">{{ $detail }}</p>
        @endif
    </div>
</{{ $href ? 'a' : 'div' }}>
