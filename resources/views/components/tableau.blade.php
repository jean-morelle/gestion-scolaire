{{-- Tableau responsive : défilement horizontal sur petit écran. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="tableau">
        @isset($entete)
            <thead><tr>{{ $entete }}</tr></thead>
        @endisset
        <tbody class="divide-y divide-slate-100 bg-white">
            {{ $slot }}
        </tbody>
    </table>
</div>
