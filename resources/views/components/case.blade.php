@props(['nom', 'label', 'coche' => false, 'aide' => null, 'valeur' => '1'])
@php
    $cleErreur = str_replace(['[', ']'], ['.', ''], $nom);
    $estCoche = old() ? (bool) old($cleErreur) : $coche;
@endphp
<div {{ $attributes->only('class') }}>
    <label class="flex items-start gap-2.5 text-sm text-slate-700">
        <input type="hidden" name="{{ $nom }}" value="0">
        <input type="checkbox" name="{{ $nom }}" value="{{ $valeur }}" @checked($estCoche)
            class="mt-0.5 size-4 rounded border-slate-300 text-marque-600 focus:ring-marque-500">
        <span>
            <span class="font-medium">{{ $label }}</span>
            @if ($aide)<span class="block text-xs text-slate-500">{{ $aide }}</span>@endif
        </span>
    </label>
    @error($cleErreur)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
