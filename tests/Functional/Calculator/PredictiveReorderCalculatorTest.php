<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Calculator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;

/**
 * Tests fonctionnels du calculateur prédictif avec base de données.
 * Scénarios US-217 : cloisonnement, moins de 3 commandes, commandes annulées.
 */
final class PredictiveReorderCalculatorTest extends KernelTestCase
{
    private PredictiveReorderCalculatorInterface $calculator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->calculator = self::getContainer()->get(PredictiveReorderCalculatorInterface::class);
    }

    /**
     * Scénario alternatif : Référence à une seule entreprise (cloisonnement).
     * Deux entreprises, chacune a ses propres intervalles, aucun mélange.
     */
    public function testCalculateRespectsScopeByCompany(): void
    {
        // TODO: créer fixtures (2 entreprises, 1 référence commandée différemment par chacune)
        // Acme: 3 commandes espacées de 20 jours
        // BuildCo: 3 commandes espacées de 30 jours
        // Vérifier que calculate($variant, $acme)->medianIntervalDays === 20.0
        // Vérifier que calculate($variant, $buildco)->medianIntervalDays === 30.0

        $this->markTestIncomplete('Fixtures et assertions à implémenter');
    }

    /**
     * Scénario erreur : Moins de 3 commandes.
     * Une référence commandée 2 fois, raison "historique insuffisant" conservée.
     */
    public function testCalculateReturnsInsufficientHistoryWhenLessThan3Orders(): void
    {
        // TODO: créer fixtures (1 entreprise, 1 référence commandée 2 fois)
        // Vérifier que calculate($variant, $company)->reason === 'historique insuffisant'

        $this->markTestIncomplete('Fixtures et assertions à implémenter');
    }

    /**
     * Scénario erreur évitée : Commandes annulées.
     * Une commande annulée exclue du calcul, mêmes règles que company-insights.
     */
    public function testCalculateExcludesCancelledOrders(): void
    {
        // TODO: créer fixtures (1 entreprise, 1 référence : 3 commandes complétées + 1 annulée)
        // Vérifier que calculate($variant, $company) compte seulement les 3 commandes complétées

        $this->markTestIncomplete('Fixtures et assertions à implémenter');
    }
}
