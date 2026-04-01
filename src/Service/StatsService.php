<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\DashboardDto;
use App\DTO\MonthlyStatsDto;
use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use App\Repository\DepenseRepository;

/**
 * Agrégation des statistiques mensuelles et du tableau de bord.
 */
class StatsService
{
    public function __construct(
        private readonly BudgetForecastService $budgetForecastService,
        private readonly DepenseRepository $depenseRepository,
        private readonly CategorieRepository $categorieRepository,
        private readonly RecommendationService $recommendationService,
    ) {
    }

    /**
     * @return list<MonthlyStatsDto>
     */
    public function getMonthlyStats(int $year, int $month): array
    {
        $forecastByCat = $this->budgetForecastService->getMonthlyForecastByCategory();
        $sums = $this->depenseRepository->getSumByCategoryForMonth($year, $month);
        $reelByCat = [];
        foreach ($sums as $row) {
            $reelByCat[$row['categorieCode']] = $row['total'];
        }

        /** @var list<Categorie> $categories */
        $categories = $this->categorieRepository->findBy([], ['ordre' => 'ASC', 'code' => 'ASC']);

        $out = [];
        foreach ($categories as $cat) {
            $code = $cat->getCode();
            $prevision = (float) ($forecastByCat[$code] ?? 0.0);
            $reel = (int) ($reelByCat[$code] ?? 0);
            $out[] = new MonthlyStatsDto(
                categorieCode: $code,
                categorieLibelle: $cat->getLibelle(),
                couleur: $cat->getCouleur(),
                prevision: $prevision,
                reel: $reel,
                ecart: $reel - (int) round($prevision),
                pourcentage: $prevision > 0.0 ? ($reel / $prevision) * 100.0 : 0.0,
            );
        }

        foreach (array_keys($reelByCat) as $code) {
            if ($this->findDtoByCode($out, $code) !== null) {
                continue;
            }
            $out[] = new MonthlyStatsDto(
                categorieCode: $code,
                categorieLibelle: $code,
                couleur: '#6B7280',
                prevision: 0.0,
                reel: $reelByCat[$code],
                ecart: $reelByCat[$code],
                pourcentage: 0.0,
            );
        }

        return $out;
    }

    /**
     * @param list<MonthlyStatsDto> $list
     */
    private function findDtoByCode(array $list, string $code): ?MonthlyStatsDto
    {
        foreach ($list as $dto) {
            if ($dto->categorieCode === $code) {
                return $dto;
            }
        }

        return null;
    }

    public function getDashboard(int $year, int $month): DashboardDto
    {
        $stats = $this->getMonthlyStats($year, $month);
        $totalPrevu = 0;
        $totalDep = 0;
        foreach ($stats as $s) {
            $totalPrevu += (int) round($s->prevision);
            $totalDep += $s->reel;
        }

        return new DashboardDto(
            totalPrevuMois: $totalPrevu,
            totalDepenseMois: $totalDep,
            ecartMois: $totalDep - $totalPrevu,
            totalAujourdhui: $this->depenseRepository->getTodayTotal(),
            joursRestants: $this->computeJoursRestantsDansMois($year, $month),
            categorieTopMois: $this->pickTopCategory($stats),
            statsByCategory: $stats,
            dailyTrend: $this->depenseRepository->getDailyTotalsForLastDays(30),
            alertes: $this->recommendationService->generate($year, $month),
        );
    }

    /**
     * @return list<\App\DTO\RecommendationDto>
     */
    public function getRecommendations(int $year, int $month): array
    {
        return $this->recommendationService->generate($year, $month);
    }

    /**
     * Jours restants dans le mois courant (après aujourd'hui jusqu'à fin de mois, borné au mois demandé).
     */
    private function computeJoursRestantsDansMois(int $year, int $month): int
    {
        $today = new \DateTimeImmutable('today');
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        if ($today < $first || $today > $last) {
            return (int) $first->diff($last)->days;
        }
        $tomorrow = $today->modify('+1 day');
        if ($tomorrow > $last) {
            return 0;
        }

        return (int) $tomorrow->diff($last)->days + 1;
    }

    /**
     * @param list<MonthlyStatsDto> $stats
     */
    private function pickTopCategory(array $stats): string
    {
        $top = '';
        $max = -1;
        foreach ($stats as $s) {
            if ($s->reel > $max) {
                $max = $s->reel;
                $top = $s->categorieCode;
            }
        }

        return $top;
    }
}
