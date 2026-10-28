<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Integration;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\TmonierSyliusPredictiveAnalyticsPlugin;

/**
 * Test d'installation : vérifie que le plugin peut être installé dans un projet Sylius.
 * Règle 33 : premier test d'un nouveau paquet.
 *
 * Ce test échoue au départ car le calculateur n'existe pas encore.
 */
final class InstallationTest extends TestCase
{
    public function testPluginClassExists(): void
    {
        $this->assertTrue(class_exists(TmonierSyliusPredictiveAnalyticsPlugin::class));
    }

    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new TmonierSyliusPredictiveAnalyticsPlugin();
        $this->assertInstanceOf(TmonierSyliusPredictiveAnalyticsPlugin::class, $plugin);
    }

    public function testCalculatorInterfaceExists(): void
    {
        // Ce test échoue tant que l'interface n'existe pas
        $this->assertTrue(interface_exists(PredictiveReorderCalculatorInterface::class));
    }
}
