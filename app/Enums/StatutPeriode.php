<?php

namespace App\Enums;

enum StatutPeriode: string
{
    case Ouverte = 'ouverte';
    case Cloturee = 'cloturee';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouverte => 'Ouverte',
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
