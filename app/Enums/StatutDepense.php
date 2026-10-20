<?php

namespace App\Enums;

enum StatutDepense: string
{
    case Valide = 'valide';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::Valide => 'Valide',
            self::Annulee => 'Annulée',
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
