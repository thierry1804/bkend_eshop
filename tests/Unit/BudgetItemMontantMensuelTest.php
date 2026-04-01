<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\BudgetItem;
use App\Entity\Categorie;
use PHPUnit\Framework\TestCase;

final class BudgetItemMontantMensuelTest extends TestCase
{
    private function makeItem(): BudgetItem
    {
        $cat = (new Categorie())->setCode('t')->setLibelle('Test');

        return (new BudgetItem())
            ->setNom('Ligne')
            ->setCategorie($cat)
            ->setPeriodicite('MOIS')
            ->setQuantite(2.0)
            ->setPrixUnitaire(150);
    }

    public function testFrequenceParDefautUnEtMontantMensuel(): void
    {
        $b = $this->makeItem();
        self::assertSame(1, $b->getFrequence());
        self::assertSame(300.0, $b->getMontantMensuel());
    }

    public function testFrequenceAugmenteMontantMensuel(): void
    {
        $b = $this->makeItem()->setFrequence(3);
        self::assertSame(900.0, $b->getMontantMensuel());
    }

    public function testPeriodiciteSemaineEtFrequence(): void
    {
        $b = $this->makeItem()
            ->setPeriodicite('SEMAINE')
            ->setQuantite(1.0)
            ->setPrixUnitaire(100)
            ->setFrequence(2);
        self::assertSame(866.0, $b->getMontantMensuel());
    }
}
