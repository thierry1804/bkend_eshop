<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BudgetItem;
use App\Entity\Depense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Depense>
 */
class DepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depense::class);
    }

    /**
     * @return list<Depense>
     */
    public function findByMonth(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');

        return $this->createQueryBuilder('d')
            ->andWhere('d.date >= :start')
            ->andWhere('d.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('d.date', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{categorieCode: string, total: int}>
     */
    public function getSumByCategoryForMonth(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');

        $rows = $this->createQueryBuilder('d')
            ->select('d.categorieCode AS categorieCode')
            ->addSelect('SUM(d.montant) AS total')
            ->andWhere('d.date >= :start')
            ->andWhere('d.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('d.categorieCode')
            ->orderBy('d.categorieCode', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'categorieCode' => (string) $row['categorieCode'],
                'total' => (int) $row['total'],
            ];
        }

        return $out;
    }

    /**
     * Total dépensé aujourd'hui (timezone serveur).
     */
    public function getTodayTotal(): int
    {
        $today = new \DateTimeImmutable('today');

        $sum = $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.montant), 0)')
            ->andWhere('d.date = :day')
            ->setParameter('day', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $sum;
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    public function getDailyTotalsForLastDays(int $days = 30): array
    {
        $end = new \DateTimeImmutable('today');
        $start = $end->modify('-'.($days - 1).' days');

        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<'SQL'
            SELECT DATE(`date`) AS d, SUM(montant) AS total
            FROM depenses
            WHERE `date` >= :start AND `date` <= :end
            GROUP BY DATE(`date`)
            ORDER BY d ASC
            SQL;

        $rows = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ])->fetchAllAssociative();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'date' => (string) $row['d'],
                'total' => (int) $row['total'],
            ];
        }

        return $out;
    }

    /**
     * Filtres collection : date, categorieCode, produit, tri, pagination.
     *
     * @return array{items: list<Depense>, total: int}
     */
    public function findFiltered(
        ?\DateTimeImmutable $after,
        ?\DateTimeImmutable $before,
        ?\DateTimeImmutable $strictAfter,
        ?\DateTimeImmutable $strictBefore,
        ?string $categorieCode,
        ?string $produitPart,
        ?string $orderField,
        string $orderDir,
        int $page,
        int $itemsPerPage,
    ): array {
        $applyFilters = function (\Doctrine\ORM\QueryBuilder $qb) use ($after, $before, $strictAfter, $strictBefore, $categorieCode, $produitPart): void {
            if (null !== $strictAfter) {
                $qb->andWhere('d.date > :strictAfter')->setParameter('strictAfter', $strictAfter);
            } elseif (null !== $after) {
                $qb->andWhere('d.date >= :after')->setParameter('after', $after);
            }
            if (null !== $strictBefore) {
                $qb->andWhere('d.date < :strictBefore')->setParameter('strictBefore', $strictBefore);
            } elseif (null !== $before) {
                $qb->andWhere('d.date <= :before')->setParameter('before', $before);
            }
            if (null !== $categorieCode && '' !== $categorieCode) {
                $qb->andWhere('d.categorieCode = :cc')->setParameter('cc', $categorieCode);
            }
            if (null !== $produitPart && '' !== $produitPart) {
                $qb->andWhere('LOWER(d.produit) LIKE :prod')
                    ->setParameter('prod', '%'.mb_strtolower($produitPart).'%');
            }
        };

        $countQb = $this->createQueryBuilder('d')->select('COUNT(d.id)');
        $applyFilters($countQb);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.budgetItem', 'bi')->addSelect('bi')
            ->leftJoin('bi.categorie', 'bc')->addSelect('bc');
        $applyFilters($qb);

        $field = match ($orderField) {
            'montant' => 'd.montant',
            default => 'd.date',
        };
        $dir = 'desc' === strtolower($orderDir) ? 'DESC' : 'ASC';
        $qb->orderBy($field, $dir)->addOrderBy('d.id', 'DESC');

        $qb->setFirstResult(max(0, ($page - 1) * $itemsPerPage))
            ->setMaxResults($itemsPerPage);

        /** @var list<Depense> $items */
        $items = $qb->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<BudgetItem>
     */
    public function findBudgetItemsNotYetPurchasedThisMonth(int $year, int $month): array
    {
        /** @var list<BudgetItem> $active */
        $active = $this->getEntityManager()->getRepository(BudgetItem::class)
            ->createQueryBuilder('b')
            ->innerJoin('b.categorie', 'c')
            ->addSelect('c')
            ->andWhere('b.actif = true')
            ->getQuery()
            ->getResult();

        $depenses = $this->findByMonth($year, $month);
        $purchasedBudgetIds = [];
        $purchasedKeys = [];
        foreach ($depenses as $d) {
            $bi = $d->getBudgetItem();
            if (null !== $bi && null !== $bi->getId()) {
                $purchasedBudgetIds[$bi->getId()] = true;
            }
            $key = mb_strtolower($d->getCategorieCode()).'|'.mb_strtolower(trim($d->getProduit()));
            $purchasedKeys[$key] = true;
        }

        $notPurchased = [];
        foreach ($active as $item) {
            $id = $item->getId();
            if (null !== $id && isset($purchasedBudgetIds[$id])) {
                continue;
            }
            $key = mb_strtolower($item->getCategorie()->getCode()).'|'.mb_strtolower(trim($item->getNom()));
            if (isset($purchasedKeys[$key])) {
                continue;
            }
            $notPurchased[] = $item;
        }

        return $notPurchased;
    }
}
