<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Occurrences mensuelles pour une périodicité donnée (fréquence unitaire = 1),
 * aligné sur la logique front (équivalent mensuel = quantite × prix × base × frequence).
 */
final class BudgetPeriodicite
{
    /**
     * Nombre d'occurrences par mois pour une occurrence « unitaire » (frequence = 1).
     */
    public static function occurrencesParMoisPourPeriodicite(string $periodicite): float
    {
        return match ($periodicite) {
            'SEMAINE' => 4.33,
            '2_SEMAINES' => 2.165,
            'TRIMESTRE' => 1 / 3,
            'ANNEE' => 1 / 12,
            default => 1.0,
        };
    }

    /**
     * Occurrences par mois = base(periodicite) × frequence (entier ≥ 1).
     */
    public static function occurrencesParMois(string $periodicite, int $frequence): float
    {
        return self::occurrencesParMoisPourPeriodicite($periodicite) * $frequence;
    }
}
