<?php

namespace App\Enums;

enum LienParente: string
{
    case Pere = 'pere';
    case Mere = 'mere';
    case Tuteur = 'tuteur';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Pere => 'Père',
            self::Mere => 'Mère',
            self::Tuteur => 'Tuteur',
            self::Autre => 'Autre',
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
