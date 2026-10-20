@props(['titre' => null])
@php
    $user = auth()->user();
    $menu = \App\Support\Navigation::pour($user);
    $nonLues = $user->unreadNotifications()->count();
    $annee = annee_active();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titre ? $titre.' · ' : '' }}{{ etablissement()->nom }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ menuOuvert: false }">
    {{-- Fond du menu mobile --}}
    <div x-cloak x-show="menuOuvert" x-transition.opacity @click="menuOuvert = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

    <aside class="no-print fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-marque-900 text-marque-100 transition-transform lg:translate-x-0"
           :class="menuOuvert && 'translate-x-0'">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            @if (etablissement()->logo_chemin)
                <img src="{{ route('etablissement.logo') }}" alt="" class="size-9 rounded-lg bg-white object-contain p-0.5">
            @else
                <span class="grid size-9 place-items-center rounded-lg bg-white/15 text-sm font-bold text-white">{{ mb_substr(etablissement()->sigle ?: etablissement()->nom, 0, 2) }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">{{ etablissement()->sigle ?: etablissement()->nom }}</p>
                <p class="text-xs text-marque-200">{{ $annee ? 'Année '.$annee->libelle : 'Aucune année active' }}</p>
            </div>
            <button type="button" class="ml-auto lg:hidden" @click="menuOuvert = false" aria-label="Fermer le menu"><x-icone nom="fermer" /></button>
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Navigation principale">
            @foreach ($menu as $section)
                <div>
                    @if ($section['titre'])
                        <p class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-marque-200/70 uppercase">{{ $section['titre'] }}</p>
                    @endif
                    <ul class="space-y-0.5">
                        @foreach ($section['liens'] as $lien)
                            @php $actif = request()->routeIs(...explode('|', $lien['actif'])); @endphp
                            <li>
                                <a href="{{ route($lien['route']) }}" @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                                    'bg-white/15 font-semibold text-white' => $actif,
                                    'text-marque-100 hover:bg-white/10 hover:text-white' => ! $actif,
                                ]) @if ($actif) aria-current="page" @endif>
                                    <x-icone :nom="$lien['icone']" class="size-5 shrink-0 opacity-90" />
                                    <span class="truncate">{{ $lien['libelle'] }}</span>
                                    @if ($lien['route'] === 'notifications.index' && $nonLues > 0)
                                        <span class="ml-auto rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-slate-900">{{ $nonLues }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="lg:pl-64">
        <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="menuOuvert = true" aria-label="Ouvrir le menu">
                <x-icone nom="menu" />
            </button>
            @if (\App\Http\Controllers\RechercheController::disponible($user))
                <form method="GET" action="{{ route('recherche') }}" role="search" class="relative w-full max-w-md"
                      x-data="recherche('{{ route('recherche') }}')" @click.outside="ouvert = false" @keydown.escape="ouvert = false">
                    <x-icone nom="recherche" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" x-model="q" @input.debounce.250ms="chercher()" @focus="ouvert = resultats.length > 0" autocomplete="off"
                           placeholder="Rechercher un élève, un enseignant, une classe…" aria-label="Recherche globale"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pr-3 pl-9 text-sm placeholder:text-slate-400 focus:border-marque-500 focus:bg-white focus:ring-2 focus:ring-marque-500/30 focus:outline-none">
                    <div x-cloak x-show="ouvert" class="absolute top-full left-0 z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                        <template x-for="r in resultats" :key="r.url">
                            <a :href="r.url" class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-slate-50">
                                <span class="min-w-0"><span class="block truncate font-medium text-slate-800" x-text="r.titre"></span><span class="block truncate text-xs text-slate-500" x-text="r.detail"></span></span>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600" x-text="r.categorie"></span>
                            </a>
                        </template>
                        <p x-show="resultats.length === 0" class="px-3 py-2 text-sm text-slate-500">Aucun résultat.</p>
                    </div>
                </form>
            @else
                <p class="truncate text-sm font-medium text-slate-500">{{ etablissement()->nom }}</p>
            @endif
            <div class="ml-auto flex items-center gap-2" x-data="{ ouvert: false }" @click.outside="ouvert = false">
                <a href="{{ route('notifications.index') }}" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Notifications">
                    <x-icone nom="cloche" />
                    @if ($nonLues > 0)
                        <span class="absolute top-1 right-1 size-2.5 rounded-full bg-amber-500 ring-2 ring-white"></span>
                    @endif
                </a>
                <button type="button" @click="ouvert = ! ouvert" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100" :aria-expanded="ouvert">
                    <span class="grid size-8 place-items-center rounded-full bg-marque-100 text-sm font-semibold text-marque-800">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm font-medium text-slate-800">{{ $user->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $user->getRoleNames()->map(fn ($r) => \App\Enums\Role::from($r)->libelle())->join(', ') }}</span>
                    </span>
                </button>
                <div x-cloak x-show="ouvert" x-transition class="absolute top-14 right-4 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                    <a href="{{ route('mot-de-passe.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><x-icone nom="cle" class="size-4" /> Changer mon mot de passe</a>
                    <form method="POST" action="{{ route('deconnexion') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50"><x-icone nom="deconnexion" class="size-4" /> Se déconnecter</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
