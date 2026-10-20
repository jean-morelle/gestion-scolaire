<?php

namespace App\Enums;

enum TypeRessource: string
{
    case Chapitre = 'chapitre';
    case Document = 'document';
    case Exercice = 'exercice';
    case Lien = 'lien';

    public function libelle(): string
    {
        return match ($this) {
            self::Chapitre => 'Chapitre',
            self::Document => 'Document',
            self::Exercice => 'Exercice',
            self::Lien => 'Lien',
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
