<?php

namespace App\Enums;

enum TypeEvaluation: string
{
    case Devoir = 'devoir';
    case Interrogation = 'interrogation';
    case Composition = 'composition';

    public function libelle(): string
    {
        return match ($this) {
            self::Devoir => 'Devoir',
            self::Interrogation => 'Interrogation',
            self::Composition => 'Composition',
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
