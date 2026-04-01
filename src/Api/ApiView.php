<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\BudgetItem;
use App\Entity\Categorie;
use App\Entity\Depense;

/**
 * Représentation JSON des entités pour l'API REST (sans sérialiseur Symfony dédié).
 */
final class ApiView
{
    /**
     * @return array<string, mixed>
     */
    public static function categorie(Categorie $c): array
    {
        return [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'libelle' => $c->getLibelle(),
            'couleur' => $c->getCouleur(),
            'icone' => $c->getIcone(),
            'ordre' => $c->getOrdre(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function categorieCompact(Categorie $c): array
    {
        return [
            'code' => $c->getCode(),
            'libelle' => $c->getLibelle(),
            'couleur' => $c->getCouleur(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function budgetItem(BudgetItem $b): array
    {
        return [
            'id' => $b->getId(),
            'nom' => $b->getNom(),
            'categorie' => self::categorieCompact($b->getCategorie()),
            'periodicite' => $b->getPeriodicite(),
            'frequence' => $b->getFrequence(),
            'quantite' => $b->getQuantite(),
            'unite' => $b->getUnite(),
            'prixUnitaire' => $b->getPrixUnitaire(),
            'montant' => $b->getMontant(),
            'montantMensuel' => $b->getMontantMensuel(),
            'actif' => $b->isActif(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function depense(Depense $d): array
    {
        $bi = $d->getBudgetItem();

        return [
            'id' => $d->getId(),
            'date' => $d->getDate()->format('Y-m-d'),
            'produit' => $d->getProduit(),
            'budgetItem' => $bi ? self::budgetItemNested($bi) : null,
            'categorieCode' => $d->getCategorieCode(),
            'quantite' => $d->getQuantite(),
            'unite' => $d->getUnite(),
            'prixUnitaire' => $d->getPrixUnitaire(),
            'montant' => $d->getMontant(),
            'note' => $d->getNote(),
            'createdAt' => $d->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function budgetItemNested(BudgetItem $b): array
    {
        return [
            'id' => $b->getId(),
            'nom' => $b->getNom(),
            'categorie' => self::categorieCompact($b->getCategorie()),
        ];
    }
}
