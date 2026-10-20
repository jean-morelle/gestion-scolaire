<?php

namespace App\Enums;

enum StatutPresence: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Retard = 'retard';
    case AbsentExcuse = 'absent_excuse';

    public function libelle(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Retard => 'Retard',
            self::AbsentExcuse => 'Absence excusée',
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
