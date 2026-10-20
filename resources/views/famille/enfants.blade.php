<x-layouts.app titre="Mes enfants">
    @php $d = (int) etablissement()->parametre('decimales', 2); @endphp
    <x-entete :titre="'Bonjour '.auth()->user()->name" sous-titre="Suivi de la scolarité de vos enfants." />

    @if ($resumes->isEmpty())
        <x-carte><x-vide message="Aucun enfant n'est encore rattaché à votre compte. Contactez le secrétariat de l'établissement." icone="utilisateurs" /></x-carte>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($resumes as $r)
                @php $e = $r['eleve']; $i = $r['inscription']; @endphp
                <x-carte>
                    <div class="flex items-start gap-4">
                        @if ($e->photo_chemin)
                            <img src="{{ route('eleves.photo', $e) }}" alt="" class="size-16 rounded-xl object-cover ring-1 ring-slate-200">
                        @else
                            <span class="grid size-16 shrink-0 place-items-center rounded-xl bg-marque-50 text-xl font-semibold text-marque-700">{{ mb_substr($e->prenoms, 0, 1) }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $e->prenoms }} {{ mb_strtoupper($e->nom) }}</h2>
                            <p class="text-sm text-slate-500">{{ $i ? $i->classe->nom.' · '.$i->anneeScolaire->libelle : 'Pas d\'inscription en cours' }}</p>
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs text-slate-500">Dernier bulletin</dt>
                            @if ($r['dernierBulletin'])
                                <dd class="text-lg font-semibold">{{ note_fr($r['dernierBulletin']->moyenne_generale, $d) }}<span class="text-xs font-normal">/20</span></dd>
                                <dd class="text-xs text-slate-500">{{ $r['dernierBulletin']->periode->libelle }} · rang {{ $r['dernierBulletin']->rang ?? '—' }}/{{ $r['dernierBulletin']->effectif }}</dd>
                            @else
                                <dd class="text-slate-500">Pas encore publié</dd>
                            @endif
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs text-slate-500">Absences {{ $r['periode'] ? '('.$r['periode']->libelle.')' : '' }}</dt>
                            @if ($r['assiduite'])
                                <dd class="text-lg font-semibold">{{ $r['assiduite']['absences'] }} <span class="text-xs font-normal">demi-j.</span></dd>
                                <dd @class(['text-xs', 'text-red-700 font-medium' => $r['assiduite']['non_justifiees'] > 0, 'text-slate-500' => ! $r['assiduite']['non_justifiees']])>{{ $r['assiduite']['non_justifiees'] }} non justifiée(s) · {{ $r['assiduite']['retards'] }} retard(s)</dd>
                            @else
                                <dd class="text-slate-500">—</dd>
                            @endif
                        </div>
                        <div class="col-span-2 rounded-xl bg-slate-50 p-3 sm:col-span-1">
                            <dt class="text-xs text-slate-500">Reste à payer</dt>
                            @if ($r['finances'] && $r['finances']['du'] > 0)
                                <dd class="text-lg font-semibold">{{ montant($r['finances']['solde']) }}</dd>
                                <dd @class(['text-xs', 'text-red-700 font-medium' => $r['finances']['retard'] > 0, 'text-emerald-700' => ! $r['finances']['retard']])>{{ $r['finances']['retard'] > 0 ? 'dont '.montant($r['finances']['retard']).' en retard' : 'À jour' }}</dd>
                            @else
                                <dd class="text-slate-500">—</dd>
                            @endif
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-bouton petit :href="route('famille.enfant', $e)">Suivi complet</x-bouton>
                        <x-bouton petit variante="secondaire" icone="diplome" :href="route('famille.enfant', [$e, 'resultats'])">Résultats</x-bouton>
                        <x-bouton petit variante="secondaire" icone="coche" :href="route('famille.enfant', [$e, 'assiduite'])">Absences</x-bouton>
                        <x-bouton petit variante="secondaire" icone="portefeuille" :href="route('famille.enfant', [$e, 'finances'])">Paiements</x-bouton>
                    </div>
                </x-carte>
            @endforeach
        </div>
    @endif
</x-layouts.app>
