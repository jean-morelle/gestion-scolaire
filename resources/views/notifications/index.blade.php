<x-layouts.app titre="Notifications">
    <x-entete titre="Notifications">
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.tout-lire') }}">
                @csrf
                <x-bouton variante="secondaire">Tout marquer comme lu</x-bouton>
            </form>
        @endif
    </x-entete>

    <x-carte sans-marge>
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.ouvrir', $notification->id) }}"
               @class(['flex items-start gap-3 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50', 'bg-marque-50/50' => ! $notification->read_at])>
                <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-amber-500' => ! $notification->read_at, 'bg-slate-200' => $notification->read_at])></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-slate-900">{{ $notification->data['titre'] ?? 'Notification' }}</span>
                    <span class="block text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                    <span class="mt-0.5 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <x-vide message="Vous n'avez aucune notification." icone="cloche" />
        @endforelse
        @if ($notifications->hasPages())
            <div class="p-4">{{ $notifications->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
