<?php

namespace App\Enums;

enum CibleAnnonce: string
{
    case Tous = 'tous';
    case Personnel = 'personnel';
    case Parents = 'parents';
    case Eleves = 'eleves';

    public function libelle(): string
    {
        return match ($this) {
            self::Tous => 'Tout le monde',
            self::Personnel => 'Personnel',
            self::Parents => 'Parents',
            self::Eleves => 'Élèves',
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
