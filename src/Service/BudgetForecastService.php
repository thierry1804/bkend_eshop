<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BudgetItem;
use App\Repository\BudgetItemRepository;

/**
 * Conversion des montants budgétaires en équivalents mensuels.
 */
class BudgetForecastService
{
    public function __construct(
        private readonly BudgetItemRepository $budgetItemRepository,
    ) {
    }

    /**
     * Convertit une ligne de budget en montant mensuel équivalent (Ariary).
     */
    public function toMonthlyAmount(BudgetItem $item): float
    {
        return $item->getMontantMensuel();
    }

    /**
     * Prévision mensuelle agrégée par code de catégorie.
     *
     * @return array<string, float>
     */
    public function getMonthlyForecastByCategory(): array
    {
        $items = $this->budgetItemRepository->createQueryBuilder('b')
            ->innerJoin('b.categorie', 'c')
            ->addSelect('c')
            ->andWhere('b.actif = true')
            ->getQuery()
            ->getResult();

        $byCat = [];
        foreach ($items as $item) {
            $code = $item->getCategorie()->getCode();
            if (!isset($byCat[$code])) {
                $byCat[$code] = 0.0;
            }
            $byCat[$code] += $this->toMonthlyAmount($item);
        }

        return $byCat;
    }
}
