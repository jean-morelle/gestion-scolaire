<?php

namespace App\Enums;

enum ModePaiement: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case Virement = 'virement';
    case Cheque = 'cheque';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::MobileMoney => 'Mobile Money',
            self::Virement => 'Virement',
            self::Cheque => 'Chèque',
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
