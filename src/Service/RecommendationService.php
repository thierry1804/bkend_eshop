<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MonthlyStatsDto;
use App\DTO\RecommendationDto;
use App\Repository\CategorieRepository;
use App\Repository\DepenseRepository;

/**
 * Règles métier : alertes budgétaires et rappels d'achats.
 */
class RecommendationService
{
    public function __construct(
        private readonly BudgetForecastService $budgetForecastService,
        private readonly DepenseRepository $depenseRepository,
        private readonly CategorieRepository $categorieRepository,
    ) {
    }

    /**
     * @return list<RecommendationDto>
     */
    public function generate(int $year, int $month): array
    {
        $stats = $this->buildMonthlyStats($year, $month);
        $out = [];

        foreach ($stats as $s) {
            $this->appendBudgetAlerts($out, $s);
        }

        foreach ($this->depenseRepository->findBudgetItemsNotYetPurchasedThisMonth($year, $month) as $item) {
            $out[] = new RecommendationDto(
                type: 'not_purchased',
                severity: 'info',
                titre: 'Achat prévu non enregistré',
                message: sprintf('%s (%s) n\'a pas encore été acheté ce mois.', $item->getNom(), $item->getCategorie()->getLibelle()),
                categorieCode: $item->getCategorie()->getCode(),
                produit: $item->getNom(),
            );
        }

        $this->appendUnderBudgetSuccess($out, $stats, $year, $month);

        return $out;
    }

    /**
     * @return list<MonthlyStatsDto>
     */
    private function buildMonthlyStats(int $year, int $month): array
    {
        $forecastByCat = $this->budgetForecastService->getMonthlyForecastByCategory();
        $sums = $this->depenseRepository->getSumByCategoryForMonth($year, $month);
        $reelByCat = [];
        foreach ($sums as $row) {
            $reelByCat[$row['categorieCode']] = $row['total'];
        }

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

        return $out;
    }

    /**
     * @param list<RecommendationDto> $out
     */
    private function appendBudgetAlerts(array &$out, MonthlyStatsDto $s): void
    {
        if ($s->prevision <= 0.0) {
            return;
        }
        $ratio = $s->reel / $s->prevision;
        $pct = $ratio * 100.0;
        if ($ratio > 1.0) {
            $out[] = new RecommendationDto(
                type: 'over_budget',
                severity: 'danger',
                titre: sprintf('%s dépasse le budget', $s->categorieLibelle),
                message: sprintf(
                    '%s a dépassé la prévision mensuelle de %d%%',
                    $s->categorieLibelle,
                    (int) round($pct - 100)
                ),
                categorieCode: $s->categorieCode,
                pourcentage: round($pct, 1),
            );

            return;
        }
        if ($ratio >= 0.8 && $ratio <= 1.0) {
            $out[] = new RecommendationDto(
                type: 'near_limit',
                severity: 'warning',
                titre: sprintf('%s approche la limite', $s->categorieLibelle),
                message: sprintf('%s a consommé environ %d%% du budget prévu.', $s->categorieLibelle, (int) round($pct)),
                categorieCode: $s->categorieCode,
                pourcentage: round($pct, 1),
            );
        }
    }

    /**
     * @param list<RecommendationDto>      $out
     * @param list<MonthlyStatsDto> $stats
     */
    private function appendUnderBudgetSuccess(array &$out, array $stats, int $year, int $month): void
    {
        if ($this->computeJoursRestants($year, $month) > 7) {
            return;
        }
        foreach ($stats as $s) {
            if ($s->prevision <= 0.0) {
                continue;
            }
            $ratio = $s->reel / $s->prevision;
            if ($ratio < 0.5) {
                $out[] = new RecommendationDto(
                    type: 'under_budget',
                    severity: 'success',
                    titre: sprintf('%s bien maîtrisé ce mois', $s->categorieLibelle),
                    message: sprintf('%s reste sous 50%% de la prévision en fin de période.', $s->categorieLibelle),
                    categorieCode: $s->categorieCode,
                    pourcentage: round($ratio * 100.0, 1),
                );
            }
        }
    }

    private function computeJoursRestants(int $year, int $month): int
    {
        $today = new \DateTimeImmutable('today');
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        if ($today < $first || $today > $last) {
            return 0;
        }
        $tomorrow = $today->modify('+1 day');
        if ($tomorrow > $last) {
            return 0;
        }

        return (int) $tomorrow->diff($last)->days + 1;
    }
}
