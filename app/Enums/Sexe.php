<?php

namespace App\Enums;

enum Sexe: string
{
    case Masculin = 'M';
    case Feminin = 'F';

    public function libelle(): string
    {
        return match ($this) {
            self::Masculin => 'Masculin',
            self::Feminin => 'Féminin',
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
