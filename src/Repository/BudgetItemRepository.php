<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BudgetItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetItem>
 */
class BudgetItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetItem::class);
    }

    /**
     * Postes actifs groupés par code de catégorie.
     *
     * @return array<string, array{categorie: \App\Entity\Categorie, items: list<BudgetItem>}>
     */
    public function findAllGroupedByCategory(): array
    {
        $items = $this->createQueryBuilder('b')
            ->innerJoin('b.categorie', 'c')
            ->addSelect('c')
            ->andWhere('b.actif = true')
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('b.nom', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<string, array{categorie: \App\Entity\Categorie, items: list<BudgetItem>}> $grouped */
        $grouped = [];
        foreach ($items as $item) {
            $code = $item->getCategorie()->getCode();
            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'categorie' => $item->getCategorie(),
                    'items' => [],
                ];
            }
            $grouped[$code]['items'][] = $item;
        }

        return $grouped;
    }

    /**
     * Somme des équivalents mensuels (Ariary arrondi).
     */
    public function getTotalMensuelPrevisionnel(): int
    {
        $items = $this->createQueryBuilder('b')
            ->andWhere('b.actif = true')
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($items as $item) {
            $total += $item->getMontantMensuel();
        }

        return (int) round($total);
    }

    /**
     * @return list<BudgetItem>
     */
    public function findByNomLike(string $term): array
    {
        return $this->createQueryBuilder('b')
            ->innerJoin('b.categorie', 'c')
            ->addSelect('c')
            ->andWhere('LOWER(b.nom) LIKE :t')
            ->setParameter('t', '%'.mb_strtolower($term).'%')
            ->andWhere('b.actif = true')
            ->orderBy('b.nom', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}
