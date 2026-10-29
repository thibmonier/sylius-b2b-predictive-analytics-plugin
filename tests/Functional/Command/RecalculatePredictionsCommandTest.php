<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;

/**
 * Test fonctionnel de la commande de recalcul pour toutes les entreprises.
 */
final class RecalculatePredictionsCommandTest extends KernelTestCase
{
    use PredictiveTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    /**
     * Test nominal : recalcule pour toutes les entreprises avec des commandes.
     */
    public function testRecalculateForAllCompanies(): void
    {
        // Créer 2 entreprises avec des commandes
        $companyA = $this->catalog()->company('CompanyA');
        $companyB = $this->catalog()->company('CompanyB');

        $variant = $this->catalog()->product('widget', [$this->catalog()->localeCode() => 'Widget']);

        // CompanyA : 3 commandes
        $customerA = $this->catalog()->customer('buyer-a');
        $this->catalog()->member($companyA, $customerA);
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 3; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 20) . ' days');
            $this->catalog()->orderOfLines($customerA, [[$variant, 1, 10000]], $orderDate->format('Y-m-d H:i:s'));
        }

        // CompanyB : 2 commandes (historique insuffisant)
        $customerB = $this->catalog()->customer('buyer-b');
        $this->catalog()->member($companyB, $customerB);
        for ($i = 0; $i < 2; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 15) . ' days');
            $this->catalog()->orderOfLines($customerB, [[$variant, 1, 10000]], $orderDate->format('Y-m-d H:i:s'));
        }

        // Exécuter la commande
        \assert(self::$kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application = new Application(self::$kernel);
        $command = $application->find('tmonier:predictive:recalculate');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        // Vérifier le succès
        $this->assertSame(0, $exitCode, 'La commande doit réussir');

        // Vérifier la sortie
        $output = $tester->getDisplay();
        $this->assertStringContainsString('CompanyA', $output, 'Doit afficher CompanyA');
        $this->assertStringContainsString('CompanyB', $output, 'Doit afficher CompanyB');
        $this->assertStringContainsString('1 prédiction', $output, 'CompanyA : 1 variante');
        $this->assertStringContainsString('1 prédiction', $output, 'CompanyB : 1 variante');

        // Vérifier la persistance en base
        $predictions = $this->connection()->fetchAllNumeric(
            'SELECT company_id, COUNT(*) FROM tmonier_predictive_stored_prediction GROUP BY company_id ORDER BY company_id',
        );

        $this->assertCount(2, $predictions, 'Doit avoir 2 entreprises avec prédictions');
    }
}
