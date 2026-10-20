<?php

namespace App\Enums;

enum StatutAnnee: string
{
    case Preparation = 'preparation';
    case EnCours = 'en_cours';
    case Cloturee = 'cloturee';

    public function libelle(): string
    {
        return match ($this) {
            self::Preparation => 'En préparation',
            self::EnCours => 'En cours',
            self::Cloturee => 'Clôturée',
        };
    }

    /**
     * @return array<string, string> valeur => libellé, pour les listes déroulantes.
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $cas) => [$cas->value, $cas->libelle()], self::cases()), 1, 0);
    }
}
