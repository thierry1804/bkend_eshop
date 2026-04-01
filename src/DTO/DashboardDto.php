<?php

declare(strict_types=1);

namespace App\DTO;

class DashboardDto implements \JsonSerializable
{
    /**
     * @param list<MonthlyStatsDto>  $statsByCategory
     * @param list<array{date: string, total: int}> $dailyTrend
     * @param list<RecommendationDto> $alertes
     */
    public function __construct(
        public int $totalPrevuMois,
        public int $totalDepenseMois,
        public int $ecartMois,
        public int $totalAujourdhui,
        public int $joursRestants,
        public string $categorieTopMois,
        public array $statsByCategory,
        public array $dailyTrend,
        public array $alertes,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'totalPrevuMois' => $this->totalPrevuMois,
            'totalDepenseMois' => $this->totalDepenseMois,
            'ecartMois' => $this->ecartMois,
            'totalAujourdhui' => $this->totalAujourdhui,
            'joursRestants' => $this->joursRestants,
            'categorieTopMois' => $this->categorieTopMois,
            'statsByCategory' => array_map(
                static fn (MonthlyStatsDto $s) => $s->jsonSerialize(),
                $this->statsByCategory
            ),
            'dailyTrend' => $this->dailyTrend,
            'alertes' => array_map(
                static fn (RecommendationDto $r) => $r->jsonSerialize(),
                $this->alertes
            ),
        ];
    }
}
