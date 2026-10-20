@props(['nom', 'label', 'type' => 'text', 'valeur' => null, 'aide' => null, 'requis' => false])
@php
    $id = 'champ-'.str_replace(['[', ']', '.'], '-', $nom);
    $cleErreur = str_replace(['[', ']'], ['.', ''], $nom);
    $valeurAffichee = $type === 'password' ? null : old($cleErreur, $valeur instanceof \Carbon\CarbonInterface ? $valeur->format('Y-m-d') : $valeur);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-700">
        {{ $label }} @if ($requis)<span class="text-red-500" aria-hidden="true">*</span>@endif
    </label>
    <input id="{{ $id }}" name="{{ $nom }}" type="{{ $type }}" value="{{ $valeurAffichee }}" @required($requis)
        {{ $attributes->except('class')->class(['champ', 'champ-erreur' => $errors->has($cleErreur)]) }}
        @error($cleErreur) aria-invalid="true" aria-describedby="{{ $id }}-erreur" @enderror>
    @error($cleErreur)
        <p id="{{ $id }}-erreur" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @elseif ($aide)
        <p class="mt-1 text-xs text-slate-500">{{ $aide }}</p>
    @enderror
</div>
