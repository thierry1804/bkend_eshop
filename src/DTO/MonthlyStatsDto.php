<?php

declare(strict_types=1);

namespace App\DTO;

class MonthlyStatsDto implements \JsonSerializable
{
    public function __construct(
        public string $categorieCode,
        public string $categorieLibelle,
        public string $couleur,
        public float $prevision,
        public int $reel,
        public int $ecart,
        public float $pourcentage,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'categorieCode' => $this->categorieCode,
            'categorieLibelle' => $this->categorieLibelle,
            'couleur' => $this->couleur,
            'prevision' => $this->prevision,
            'reel' => $this->reel,
            'ecart' => $this->ecart,
            'pourcentage' => $this->pourcentage,
        ];
    }
}
