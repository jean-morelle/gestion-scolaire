{{-- Grille hebdomadaire : une colonne par jour (empilées sur mobile). $vue = classe | enseignant. --}}
@php $joursAffiches = collect($jours)->filter(fn ($c, $j) => $j <= 5 || $c->isNotEmpty()); @endphp
<div @class(['grid gap-3 sm:grid-cols-2', 'lg:grid-cols-6' => $joursAffiches->count() === 6, 'lg:grid-cols-5' => $joursAffiches->count() !== 6])>
    @foreach ($joursAffiches as $numero => $creneaux)
        <div class="rounded-xl border border-slate-200 bg-slate-50/60">
            <p class="border-b border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">{{ \App\Enums\Jour::from($numero)->libelle() }}</p>
            <div class="space-y-2 p-2">
                @forelse ($creneaux as $creneau)
                    @php $a = $creneau->affectation; @endphp
                    <div class="rounded-lg border-l-4 bg-white px-3 py-2 text-sm shadow-xs" style="border-left-color: {{ $a->matiere->couleur ?? '#94a3b8' }}">
                        <p class="text-xs font-medium text-slate-500">{{ $creneau->horaire() }}@if ($creneau->salle) · {{ $creneau->salle }}@endif</p>
                        <p class="font-medium text-slate-900">{{ $a->matiere->libelle }}</p>
                        <p class="text-xs text-slate-600">
                            @if ($vue === 'classe')
                                {{ $a->enseignant?->user->name ?? 'Enseignant non désigné' }}
                            @else
                                {{ $a->classe->nom }}
                            @endif
                        </p>
                        @if ($modifiable ?? false)
                            <form method="POST" action="{{ route('emplois-du-temps.destroy', $creneau) }}" class="no-print mt-1" data-confirm="Supprimer ce créneau ({{ $a->matiere->libelle }}, {{ $creneau->horaire() }}) ?">
                                @csrf @method('DELETE')
                                <button class="text-xs font-medium text-red-600 hover:underline">Supprimer</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-1 py-2 text-xs text-slate-400">Aucun cours</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
