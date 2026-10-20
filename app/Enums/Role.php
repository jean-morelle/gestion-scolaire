<?php

namespace App\Enums;

/**
 * Rôles de l'application (noms des rôles spatie/laravel-permission). Un utilisateur peut en cumuler plusieurs.
 */
enum Role: string
{
    case Administrateur = 'administrateur';
    case Directeur = 'directeur';
    case Secretaire = 'secretaire';
    case Censeur = 'censeur';
    case Enseignant = 'enseignant';
    case Comptable = 'comptable';
    case Parent = 'parent';
    case Eleve = 'eleve';

    public function libelle(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur technique',
            self::Directeur => 'Directeur',
            self::Secretaire => 'Secrétaire',
            self::Censeur => 'Censeur / Surveillant',
            self::Enseignant => 'Enseignant',
            self::Comptable => 'Comptable / Caissier',
            self::Parent => 'Parent',
            self::Eleve => 'Élève',
        };
    }

    /**
     * Rôles attribuables depuis la gestion des comptes du personnel
     * (les comptes parent et élève sont créés depuis leur fiche).
     *
     * @return list<self>
     */
    public static function personnel(): array
    {
        return [self::Administrateur, self::Directeur, self::Secretaire, self::Censeur, self::Enseignant, self::Comptable];
    }

    /**
     * @return array<string, string> valeur => libellé, pour les listes déroulantes.
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $cas) => [$cas->value, $cas->libelle()], self::cases()), 1, 0);
    }
}
