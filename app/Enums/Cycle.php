<?php

namespace App\Enums;

enum Cycle: string
{
    case Primaire = 'primaire';
    case College = 'college';
    case Lycee = 'lycee';

    public function libelle(): string
    {
        return match ($this) {
            self::Primaire => 'Primaire',
            self::College => 'Collège',
            self::Lycee => 'Lycée',
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
