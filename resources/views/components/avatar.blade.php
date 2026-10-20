{{-- Photo de l'élève (route protégée par ElevePolicy) ou, à défaut, ses initiales. --}}
@props(['eleve' => null, 'nom' => null, 'taille' => 'size-9'])
@php
    $libelle = $eleve ? $eleve->prenoms.' '.$eleve->nom : (string) $nom;
    $initiales = $eleve
        ? mb_strtoupper(mb_substr($eleve->prenoms, 0, 1).mb_substr($eleve->nom, 0, 1))
        : mb_strtoupper(collect(preg_split('/\s+/', trim($libelle)))->filter()->take(2)->map(fn ($m) => mb_substr($m, 0, 1))->join(''));
    // Photo seulement si la personne connectée peut l'ouvrir (sinon image cassée : initiales à la place).
    $photo = $eleve?->photo_chemin && auth()->user()?->can('view', $eleve) ? route('eleves.photo', $eleve) : null;
@endphp
@if ($photo)
    <img src="{{ $photo }}" alt="" loading="lazy" {{ $attributes->merge(['class' => "$taille shrink-0 rounded-full object-cover ring-1 ring-slate-200"]) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => "$taille grid shrink-0 place-items-center rounded-full bg-marque-50 text-xs font-semibold text-marque-700 ring-1 ring-marque-100"]) }}>{{ $initiales }}</span>
@endif
