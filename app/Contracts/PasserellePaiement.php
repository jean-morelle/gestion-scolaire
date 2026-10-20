<?php

namespace App\Contracts;

use App\Models\Inscription;

/**
 * Point d'extension pour un futur paiement en ligne (ex. FedaPay, Mobile Money agrégé).
 *
 * Aucune passerelle n'est branchée : le paiement en ligne n'est pas dans le périmètre validé et rien n'est simulé.
 * Intégration prévue :
 *  1. une classe implémentant cette interface (ex. App\Services\Passerelles\FedaPay) et liée dans AppServiceProvider ;
 *  2. initier() crée la transaction chez le prestataire et renvoie l'URL de paiement ;
 *  3. le webhook du prestataire, après vérification de sa signature par verifierNotification(), appelle
 *     PaiementService::encaisser() avec le mode « en ligne » et la référence du prestataire : le reçu, l'imputation
 *     et le journal suivent alors exactement le même chemin qu'un paiement au guichet.
 */
interface PasserellePaiement
{
    /**
     * @return array{reference: string, url: string} référence chez le prestataire et adresse de paiement
     */
    public function initier(Inscription $inscription, int $montant, string $urlRetour): array;

    /**
     * Vérifie l'authenticité d'une notification du prestataire.
     *
     * @param  array<string, mixed>  $donnees
     * @return array{reference: string, montant: int, statut: string}|null null si la notification est invalide
     */
    public function verifierNotification(array $donnees, string $signature): ?array;
}
