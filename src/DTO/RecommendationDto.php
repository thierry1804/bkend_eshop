<?php

declare(strict_types=1);

namespace App\DTO;

class RecommendationDto implements \JsonSerializable
{
    public function __construct(
        public string $type,
        public string $severity,
        public string $titre,
        public string $message,
        public ?string $categorieCode = null,
        public ?string $produit = null,
        public ?float $pourcentage = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        $a = [
            'type' => $this->type,
            'severity' => $this->severity,
            'titre' => $this->titre,
            'message' => $this->message,
        ];
        if (null !== $this->categorieCode) {
            $a['categorieCode'] = $this->categorieCode;
        }
        if (null !== $this->produit) {
            $a['produit'] = $this->produit;
        }
        if (null !== $this->pourcentage) {
            $a['pourcentage'] = $this->pourcentage;
        }

        return $a;
    }
}
