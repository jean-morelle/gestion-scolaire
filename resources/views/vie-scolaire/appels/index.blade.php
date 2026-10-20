<x-layouts.app titre="Appel">
    <x-entete titre="Appel" :sous-titre="\Carbon\Carbon::parse($date)->translatedFormat('l j F Y').' · '.$creneau->libelle()" />

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <x-champ nom="date" label="Date" type="date" :valeur="$date" :max="now()->toDateString()" class="w-44" />
        <x-selection nom="creneau" label="Demi-journée" :options="\App\Enums\Creneau::options()" :valeur="$creneau" class="w-40" />
        <x-bouton variante="secondaire">Afficher</x-bouton>
    </form>

    <x-carte titre="Classes" sans-marge>
        @if ($classes->isEmpty())
            <x-vide message="Aucune classe ne vous est attribuée pour l'année en cours." icone="classe" />
        @else
            <x-tableau>
                <x-slot:entete><th>Classe</th><th class="text-right">Effectif</th><th>Appel</th><th class="text-right">Absents</th><th class="text-right">Retards</th><th></th></x-slot:entete>
                @foreach ($classes as $classe)
                    @php $appel = $appels->get($classe->id); @endphp
                    <tr>
                        <td class="font-medium">{{ $classe->nom }}</td>
                        <td class="text-right">{{ $classe->inscriptions_actives_count }}</td>
                        <td>
                            @if ($appel)
                                <x-badge couleur="vert">Fait</x-badge>
                                <span class="text-xs text-slate-500">{{ $appel->auteur?->name }}, {{ $appel->updated_at->format('H:i') }}</span>
                            @else
                                <x-badge couleur="orange">À faire</x-badge>
                            @endif
                        </td>
                        <td class="text-right">{{ $appel ? $appel->absents : '—' }}</td>
                        <td class="text-right">{{ $appel ? $appel->retards : '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('appels.saisie', [$classe, 'date' => $date, 'creneau' => $creneau->value]) }}" class="lien">
                                {{ $appel ? 'Voir / modifier' : 'Faire l\'appel' }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>

    <x-carte titre="Historique des appels" sans-marge class="mt-6">
        <x-slot:actions>
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="creneau" value="{{ $creneau->value }}">
                <select name="classe" class="champ py-1.5" onchange="this.form.submit()" aria-label="Filtrer par classe">
                    <option value="">Toutes les classes</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected(request('classe') == $c->id)>{{ $c->nom }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
        @if ($historique->isEmpty())
            <x-vide message="Aucun appel enregistré." icone="coche" />
        @else
            <x-tableau>
                <x-slot:entete><th>Date</th><th>Demi-journée</th><th>Classe</th><th class="text-right">Absents</th><th>Saisi par</th><th></th></x-slot:entete>
                @foreach ($historique as $appel)
                    <tr>
                        <td>{{ date_fr($appel->date_appel) }}</td>
                        <td>{{ $appel->creneau->libelle() }}</td>
                        <td class="font-medium">{{ $appel->classe->nom }}</td>
                        <td class="text-right">{{ $appel->absents }}</td>
                        <td>{{ $appel->auteur?->name ?? '—' }}</td>
                        <td class="text-right"><a href="{{ route('appels.saisie', [$appel->classe, 'date' => $appel->date_appel->toDateString(), 'creneau' => $appel->creneau->value]) }}" class="lien">Détail</a></td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $historique->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
