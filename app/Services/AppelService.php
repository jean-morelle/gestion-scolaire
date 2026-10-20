<?php

namespace App\Services;

use App\Enums\Creneau;
use App\Enums\StatutInscription;
use App\Enums\StatutPresence;
use App\Models\Appel;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Justificatif;
use App\Models\Periode;
use App\Models\Presence;
use App\Models\User;
use App\Notifications\AbsenceSignalee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Appel par classe, date et demi-journée (RG-PRE-01 à 08).
 */
class AppelService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Élèves attendus à l'appel : inscrits à cette date, hors élèves partis avant (RG-ELV-03).
     *
     * @return Collection<int, Inscription>
     */
    public function eleves(Classe $classe, string $date): Collection
    {
        return $classe->inscriptions()
            ->with('eleve')
            ->whereDate('inscriptions.date_inscription', '<=', $date)
            ->where(fn (Builder $q) => $q->where('inscriptions.statut', StatutInscription::Inscrit)->orWhereDate('inscriptions.date_sortie', '>', $date))
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->orderBy('eleves.nom')->orderBy('eleves.prenoms')
            ->select('inscriptions.*')
            ->get();
    }

    /**
     * RG-PRE-06 : pas d'appel le dimanche, dans le futur, hors période ou dans une période clôturée.
     */
    public function verifierDate(Classe $classe, string $date): Periode
    {
        $jour = CarbonImmutable::parse($date);
        if ($jour->isFuture() && ! $jour->isToday()) {
            throw ValidationException::withMessages(['date_appel' => 'L\'appel ne peut pas être fait pour une date future.']);
        }
        if ($jour->isSunday()) {
            throw ValidationException::withMessages(['date_appel' => 'Le dimanche n\'est pas un jour de classe.']);
        }
        if ($classe->anneeScolaire->estCloturee()) {
            throw ValidationException::withMessages(['date_appel' => 'L\'année scolaire est clôturée.']);
        }
        $periode = $classe->anneeScolaire->periodePour($jour);
        if (! $periode) {
            throw ValidationException::withMessages(['date_appel' => 'Cette date n\'appartient à aucune période de l\'année scolaire.']);
        }
        if (! $periode->estOuverte()) {
            throw ValidationException::withMessages(['date_appel' => "La période « {$periode->libelle} » est clôturée : l'appel n'est plus modifiable."]);
        }

        return $periode;
    }

    /**
     * RG-PRE-07 : l'auteur peut modifier son appel pendant le délai paramétré ; ensuite seule la vie scolaire
     * ou la direction le peut.
     */
    public function peutModifier(User $user, Appel $appel): bool
    {
        if ($user->can('presences.saisir') || $user->can('presences.justifier')) {
            return true;
        }
        $delai = (int) etablissement()->parametre('delai_modification_appel_heures', 48);

        return $appel->saisi_par === $user->id && $appel->created_at->addHours($delai)->isFuture();
    }

    public function peutFaireAppel(User $user, Classe $classe): bool
    {
        return $user->can('presences.saisir')
            || ($user->can('presences.saisir_ses_classes') && (bool) $user->enseignant?->intervientDans($classe));
    }

    /**
     * Crée ou met à jour l'appel. Une absence couverte par un justificatif devient « absence excusée ».
     *
     * @param  array<int|string, array{statut: string, minutes_retard?: ?int, remarque?: ?string}>  $lignes  inscription_id => saisie
     */
    public function enregistrer(Classe $classe, string $date, Creneau $creneau, array $lignes, User $auteur): Appel
    {
        $this->verifierDate($classe, $date);
        $attendus = $this->eleves($classe, $date)->keyBy('id');
        $manquants = $attendus->keys()->diff(array_map('intval', array_keys($lignes)));
        if ($manquants->isNotEmpty()) {
            throw ValidationException::withMessages(['lignes' => 'Indiquez la présence de chaque élève ('.$manquants->count().' sans réponse).']);
        }

        $nouvellesAbsences = [];

        $appel = DB::transaction(function () use ($classe, $date, $creneau, $lignes, $auteur, $attendus, &$nouvellesAbsences) {
            $appel = Appel::query()->where('classe_id', $classe->id)->whereDate('date_appel', $date)
                ->where('creneau', $creneau)->lockForUpdate()->first();
            $existant = (bool) $appel;
            if ($existant && ! $this->peutModifier($auteur, $appel)) {
                throw ValidationException::withMessages(['lignes' => 'Le délai de modification de cet appel est dépassé : adressez-vous à la vie scolaire.']);
            }
            $appel ??= Appel::create(['classe_id' => $classe->id, 'date_appel' => $date, 'creneau' => $creneau, 'saisi_par' => $auteur->id]);

            $avant = $appel->presences()->pluck('statut', 'inscription_id')->map(fn ($s) => $s instanceof StatutPresence ? $s->value : $s)->all();
            $justificatifs = Justificatif::query()->whereIn('inscription_id', $attendus->keys())
                ->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->get()->groupBy('inscription_id');
            $changements = [];

            foreach ($attendus as $id => $inscription) {
                $saisie = $lignes[$id];
                $statut = StatutPresence::from($saisie['statut']);
                $justificatif = $justificatifs->get($id)?->first();
                if ($statut === StatutPresence::Absent && $justificatif) {
                    $statut = StatutPresence::AbsentExcuse;
                }

                $presence = Presence::updateOrCreate(
                    ['appel_id' => $appel->id, 'inscription_id' => $id],
                    [
                        'statut' => $statut,
                        'minutes_retard' => $statut === StatutPresence::Retard ? ($saisie['minutes_retard'] ?? null) : null,
                        'remarque' => $saisie['remarque'] ?? null,
                    ],
                );
                if ($statut === StatutPresence::AbsentExcuse && $justificatif) {
                    $presence->justificatifs()->syncWithoutDetaching([$justificatif->id]);
                } elseif ($statut !== StatutPresence::AbsentExcuse) {
                    $presence->justificatifs()->detach();
                }

                if (($avant[$id] ?? null) !== $statut->value) {
                    if (isset($avant[$id])) {
                        $changements[$inscription->eleve->nom_complet] = [$avant[$id], $statut->value];
                    }
                    if (in_array($statut, [StatutPresence::Absent, StatutPresence::Retard], true)) {
                        $nouvellesAbsences[] = $presence;
                    }
                }
            }

            if ($existant) {
                $appel->touch();
                if ($changements !== []) {
                    $this->journal->enregistrer('appel.modifie', $appel, "Appel {$classe->nom} du {$appel->date_appel->format('d/m/Y')} ({$creneau->libelle()}) modifié",
                        array_map(fn ($c) => $c[0], $changements), array_map(fn ($c) => $c[1], $changements));
                }
            }

            return $appel;
        });

        $this->notifierFamilles($nouvellesAbsences);

        return $appel;
    }

    /**
     * Totaux d'assiduité d'une inscription, sur une période ou toute l'année (RG-PRE-08).
     *
     * @return array{absences: int, non_justifiees: int, retards: int, demi_journees: int}
     */
    public function totaux(Inscription $inscription, ?Periode $periode = null): array
    {
        $statuts = Presence::query()->where('inscription_id', $inscription->id)
            ->when($periode, fn (Builder $q) => $q->whereHas('appel', fn (Builder $a) => $a
                ->whereDate('date_appel', '>=', $periode->date_debut)->whereDate('date_appel', '<=', $periode->date_fin)))
            ->pluck('statut');

        return [
            'absences' => $statuts->filter(fn ($s) => in_array($s, [StatutPresence::Absent, StatutPresence::AbsentExcuse], true))->count(),
            'non_justifiees' => $statuts->filter(fn ($s) => $s === StatutPresence::Absent)->count(),
            'retards' => $statuts->filter(fn ($s) => $s === StatutPresence::Retard)->count(),
            'demi_journees' => $statuts->count(),
        ];
    }

    /**
     * @param  list<Presence>  $presences
     */
    private function notifierFamilles(array $presences): void
    {
        if ($presences === [] || ! etablissement()->parametre('notifier_absences', true)) {
            return;
        }
        foreach ($presences as $presence) {
            $presence->load(['appel', 'inscription.eleve.responsables.user']);
            foreach ($presence->inscription->eleve->responsables as $responsable) {
                $responsable->user?->notify(new AbsenceSignalee($presence));
            }
        }
    }
}
