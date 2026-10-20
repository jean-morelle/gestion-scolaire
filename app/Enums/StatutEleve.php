<?php

namespace App\Enums;

enum StatutEleve: string
{
    case Actif = 'actif';
    case Archive = 'archive';

    public function libelle(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Archive => 'Archivé',
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
