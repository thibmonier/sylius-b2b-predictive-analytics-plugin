<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\TmonierSyliusPredictiveAnalyticsPlugin;

/**
 * Test d'installation : vérifie que le plugin peut être installé dans un projet Sylius.
 * Règle 33 : premier test d'un nouveau paquet.
 *
 * Ce test vérifie que :
 * - Le bundle est enregistré et démarre correctement
 * - Le service du calculateur est résolu dans le conteneur
 */
final class InstallationTest extends KernelTestCase
{
    public function testBundleIsRegistered(): void
    {
        self::bootKernel();

        $kernel = self::$kernel;
        $this->assertNotNull($kernel);

        $bundles = $kernel->getBundles();

        $this->assertArrayHasKey(
            'TmonierSyliusPredictiveAnalyticsPlugin',
            $bundles,
            'Le bundle TmonierSyliusPredictiveAnalyticsPlugin doit être enregistré',
        );

        $this->assertInstanceOf(
            TmonierSyliusPredictiveAnalyticsPlugin::class,
            $bundles['TmonierSyliusPredictiveAnalyticsPlugin'],
        );
    }

    public function testCalculatorServiceIsResolved(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->assertTrue(
            $container->has(PredictiveReorderCalculatorInterface::class),
            'Le service PredictiveReorderCalculatorInterface doit être disponible dans le conteneur',
        );

        $calculator = $container->get(PredictiveReorderCalculatorInterface::class);

        $this->assertInstanceOf(
            PredictiveReorderCalculatorInterface::class,
            $calculator,
            'Le service résolu doit implémenter PredictiveReorderCalculatorInterface',
        );
    }
}
