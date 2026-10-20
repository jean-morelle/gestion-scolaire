<?php

namespace App\Enums;

enum TypeInscription: string
{
    case Nouvelle = 'nouvelle';
    case Reinscription = 'reinscription';

    public function libelle(): string
    {
        return match ($this) {
            self::Nouvelle => 'Nouvelle inscription',
            self::Reinscription => 'Réinscription',
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
