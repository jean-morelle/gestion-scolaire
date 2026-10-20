@props(['message' => 'Aucun élément à afficher.', 'icone' => 'liste'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-10 text-center']) }}>
    <span class="mb-3 grid size-12 place-items-center rounded-full bg-slate-100 text-slate-400"><x-icone :nom="$icone" class="size-6" /></span>
    <p class="text-sm text-slate-500">{{ $message }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
