@props(['titre' => null])
@php
    $fond = image_connexion();
    $etab = etablissement();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre ? $titre.' · ' : '' }}{{ $etab->nom }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-marque-900">
    {{-- Photo de l'établissement en fond, voilée de bleu nuit pour garder le texte lisible. --}}
    <div class="relative min-h-screen overflow-hidden">
        @if ($fond)
            <img src="{{ $fond }}" alt="" class="absolute inset-0 size-full object-cover" aria-hidden="true">
        @endif
        <div class="absolute inset-0 bg-gradient-to-r from-marque-900/95 via-marque-900/75 to-marque-900/40" aria-hidden="true"></div>

        <main class="relative mx-auto flex min-h-screen max-w-6xl flex-col items-center justify-center gap-10 px-4 py-10 lg:flex-row lg:justify-between lg:px-8">
            <section class="max-w-md text-center text-white lg:text-left">
                <div class="mb-6 flex items-center justify-center gap-3 lg:justify-start">
                    @if ($etab->logo_chemin)
                        <img src="{{ route('etablissement.logo') }}" alt="" class="size-14 rounded-xl bg-white object-contain p-1">
                    @else
                        <span class="grid size-14 place-items-center rounded-xl bg-white/15 text-white ring-1 ring-white/20"><x-icone nom="enseignant" class="size-8" /></span>
                    @endif
                    <span class="text-3xl font-bold tracking-tight">EduGestion</span>
                </div>
                <h1 class="text-2xl font-semibold leading-snug sm:text-3xl">Une seule plateforme pour toute la vie scolaire</h1>
                <p class="mt-3 hidden text-sm leading-relaxed text-marque-100 sm:block">Élèves, enseignants, notes, présences, bulletins, paiements : tout votre établissement au même endroit, pour la direction, les enseignants et les familles.</p>
            </section>

            <section class="w-full max-w-md">
                <div class="rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
                    <x-flash />
                    {{ $slot }}
                    <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-marque-50 text-marque-700"><x-icone nom="batiment" class="size-5" /></span>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Établissement scolaire</p>
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $etab->nom }}</p>
                            @if ($etab->slogan)<p class="truncate text-xs text-slate-500">{{ $etab->slogan }}</p>@endif
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
