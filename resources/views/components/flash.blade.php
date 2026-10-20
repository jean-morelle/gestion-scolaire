@php
    $messages = [
        'succes' => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'coche'],
        'erreur' => ['bg-red-50 border-red-200 text-red-800', 'alerte'],
        'avertissement' => ['bg-amber-50 border-amber-200 text-amber-900', 'alerte'],
        'info' => ['bg-marque-50 border-marque-200 text-marque-900', 'info'],
    ];
@endphp
@foreach ($messages as $cle => [$classes, $icone])
    @if (session($cle))
        <div x-data="{ visible: true }" x-show="visible" role="{{ $cle === 'erreur' ? 'alert' : 'status' }}"
             class="no-print mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $classes }}">
            <x-icone :nom="$icone" class="mt-0.5 size-5 shrink-0" />
            <div class="flex-1">{{ session($cle) }}</div>
            <button type="button" @click="visible = false" class="opacity-60 hover:opacity-100" aria-label="Fermer"><x-icone nom="fermer" class="size-4" /></button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! ($masquerErreurs ?? false))
    <div role="alert" class="no-print mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Le formulaire contient des erreurs :</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach (array_slice($errors->all(), 0, 6) as $erreur)
                <li>{{ $erreur }}</li>
            @endforeach
        </ul>
    </div>
@endif
