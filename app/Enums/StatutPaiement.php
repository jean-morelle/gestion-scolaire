<?php

namespace App\Enums;

enum StatutPaiement: string
{
    case Valide = 'valide';
    case Annule = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::Valide => 'Valide',
            self::Annule => 'Annulé',
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
