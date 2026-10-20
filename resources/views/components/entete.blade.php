@props(['titre', 'sousTitre' => null, 'retour' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($retour)
            <a href="{{ $retour }}" class="no-print mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
                <x-icone nom="retour" class="size-4" /> Retour
            </a>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $titre }}</h1>
        @if ($sousTitre)
            <p class="mt-1 text-sm text-slate-500">{{ $sousTitre }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="no-print flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
