<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\DependencyInjection;

use Doctrine\Bundle\MigrationsBundle\DependencyInjection\DoctrineMigrationsExtension;
use PHPUnit\Framework\TestCase;
use SyliusLabs\DoctrineMigrationsExtraBundle\DependencyInjection\SyliusLabsDoctrineMigrationsExtraExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\Dbal\DbalPredictiveReorderCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\DependencyInjection\TmonierSyliusPredictiveAnalyticsExtension;
use Tmonier\SyliusPredictiveAnalyticsPlugin\TmonierSyliusPredictiveAnalyticsPlugin;

final class ExtensionTest extends TestCase
{
    public function testAliasIsTheConfigurationRoot(): void
    {
        self::assertSame('tmonier_sylius_predictive_analytics', (new TmonierSyliusPredictiveAnalyticsExtension())->getAlias());
    }

    public function testLoadAliasesTheCalculatorInterfaceToTheDbalImplementation(): void
    {
        $container = new ContainerBuilder();

        (new TmonierSyliusPredictiveAnalyticsExtension())->load([], $container);

        self::assertSame(
            DbalPredictiveReorderCalculator::class,
            (string) $container->getAlias(PredictiveReorderCalculatorInterface::class),
        );
    }

    public function testPrependRegistersTheEntityMappingAndTheMigrations(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineMigrationsExtension());
        $container->registerExtension(new SyliusLabsDoctrineMigrationsExtraExtension());

        (new TmonierSyliusPredictiveAnalyticsExtension())->prepend($container);

        /** @var array<int, array{orm: array{mappings: array<string, array<string, mixed>>}}> $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine');
        /** @var array<string, array<string, mixed>> $mappings */
        $mappings = $doctrine[0]['orm']['mappings'] ?? [];
        self::assertSame('attribute', $mappings['TmonierSyliusPredictiveAnalyticsPlugin']['type']);
        self::assertSame(
            'Tmonier\SyliusPredictiveAnalyticsPlugin\Entity',
            $mappings['TmonierSyliusPredictiveAnalyticsPlugin']['prefix'],
        );

        /** @var array<int, array<string, array<string, string>>> $migrations */
        $migrations = $container->getExtensionConfig('doctrine_migrations');
        self::assertSame(
            '@TmonierSyliusPredictiveAnalyticsPlugin/src/Migrations',
            $migrations[0]['migrations_paths']['Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations'],
        );

        /** @var array<int, array<string, array<string, list<string>>>> $executedBefore */
        $executedBefore = $container->getExtensionConfig('sylius_labs_doctrine_migrations_extra');
        self::assertContains(
            'Tmonier\SyliusCompanyInsightsPlugin\Migrations',
            $executedBefore[0]['migrations']['Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations'],
        );
    }

    public function testPluginPathIsThePackageRoot(): void
    {
        $path = (new TmonierSyliusPredictiveAnalyticsPlugin())->getPath();

        self::assertFileExists($path . '/composer.json');
    }
}
