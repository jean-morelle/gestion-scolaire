<?php

namespace App\Enums;

enum StatutInscription: string
{
    case Inscrit = 'inscrit';
    case Parti = 'parti';

    public function libelle(): string
    {
        return match ($this) {
            self::Inscrit => 'Inscrit',
            self::Parti => 'Parti',
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
