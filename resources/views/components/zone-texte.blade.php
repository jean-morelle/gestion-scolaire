@props(['nom', 'label', 'valeur' => null, 'aide' => null, 'requis' => false, 'lignes' => 3])
@php
    $id = 'champ-'.str_replace(['[', ']', '.'], '-', $nom);
    $cleErreur = str_replace(['[', ']'], ['.', ''], $nom);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-700">
        {{ $label }} @if ($requis)<span class="text-red-500" aria-hidden="true">*</span>@endif
    </label>
    <textarea id="{{ $id }}" name="{{ $nom }}" rows="{{ $lignes }}" @required($requis)
        {{ $attributes->except('class')->class(['champ', 'champ-erreur' => $errors->has($cleErreur)]) }}>{{ old($cleErreur, $valeur) }}</textarea>
    @error($cleErreur)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @elseif ($aide)
        <p class="mt-1 text-xs text-slate-500">{{ $aide }}</p>
    @enderror
</div>
