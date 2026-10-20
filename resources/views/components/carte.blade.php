@props(['titre' => null, 'sansMarge' => false])
<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-xs']) }}>
    @if ($titre || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3.5">
            <h2 class="text-base font-semibold text-slate-900">{{ $titre }}</h2>
            @isset($actions)
                <div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-5' => ! $sansMarge])>
        {{ $slot }}
    </div>
</section>
