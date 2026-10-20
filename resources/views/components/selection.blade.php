@props(['nom', 'label', 'options' => [], 'valeur' => null, 'vide' => null, 'aide' => null, 'requis' => false])
@php
    $id = 'champ-'.str_replace(['[', ']', '.'], '-', $nom);
    $cleErreur = str_replace(['[', ']'], ['.', ''], $nom);
    $courant = old($cleErreur, $valeur instanceof \BackedEnum ? $valeur->value : $valeur);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-700">
        {{ $label }} @if ($requis)<span class="text-red-500" aria-hidden="true">*</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $nom }}" @required($requis)
        {{ $attributes->except('class')->class(['champ', 'champ-erreur' => $errors->has($cleErreur)]) }}>
        @if ($vide !== null)
            <option value="">{{ $vide }}</option>
        @endif
        @foreach ($options as $cle => $libelle)
            <option value="{{ $cle }}" @selected((string) $courant === (string) $cle)>{{ $libelle }}</option>
        @endforeach
    </select>
    @error($cleErreur)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @elseif ($aide)
        <p class="mt-1 text-xs text-slate-500">{{ $aide }}</p>
    @enderror
</div>
