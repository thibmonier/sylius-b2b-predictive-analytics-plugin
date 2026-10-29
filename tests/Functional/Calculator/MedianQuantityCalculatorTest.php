<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Calculator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\MedianQuantityCalculator;

/**
 * Test de MedianQuantityCalculator : calcul de la quantité médiane par entreprise.
 */
final class MedianQuantityCalculatorTest extends KernelTestCase
{
    use PredictiveTestTrait;

    private MedianQuantityCalculator $calculator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->calculator = self::getContainer()->get(MedianQuantityCalculator::class);
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_calculates_median_quantity_for_company_only(): void
    {
        $catalog = $this->catalog();

        // Deux entreprises : Acme et BuildCo
        $acme = $catalog->company('Acme');
        $buildCo = $catalog->company('BuildCo');

        // Variante Widget
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);

        // Membres d'Acme
        $claire = $catalog->customer('claire');
        $catalog->member($acme, $claire);

        // Membres de BuildCo
        $bob = $catalog->customer('bob');
        $catalog->member($buildCo, $bob);

        // Commandes d'Acme : quantités 2, 5, 3 → médiane = 3
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($claire, [[$variant, 2, 10000]], $now->modify('-80 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($claire, [[$variant, 5, 12000]], $now->modify('-52 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($claire, [[$variant, 3, 11000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        // Commandes de BuildCo : quantités 10, 20, 30 → médiane = 20
        $catalog->orderOfLines($bob, [[$variant, 10, 50000]], $now->modify('-80 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($bob, [[$variant, 20, 100000]], $now->modify('-52 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($bob, [[$variant, 30, 150000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        // Calcul de la médiane pour Acme : doit ignorer les commandes de BuildCo
        $medianAcme = $this->calculator->calculate($acme, $variant);
        self::assertSame(3, $medianAcme, 'La médiane pour Acme doit être 3 (quantités 2, 5, 3), pas 20');

        // Calcul de la médiane pour BuildCo : doit ignorer les commandes d'Acme
        $medianBuildCo = $this->calculator->calculate($buildCo, $variant);
        self::assertSame(20, $medianBuildCo, 'La médiane pour BuildCo doit être 20 (quantités 10, 20, 30), pas 3');
    }

    /**
     * @test
     */
    public function it_returns_default_when_no_orders(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);

        $median = $this->calculator->calculate($company, $variant);

        self::assertSame(1, $median, 'La médiane par défaut doit être 1 si aucune commande');
    }
}
