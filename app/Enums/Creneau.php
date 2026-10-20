<?php

namespace App\Enums;

enum Creneau: string
{
    case Matin = 'matin';
    case ApresMidi = 'apres_midi';

    public function libelle(): string
    {
        return match ($this) {
            self::Matin => 'Matin',
            self::ApresMidi => 'Après-midi',
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
