<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class TmonierSyliusPredictiveAnalyticsExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusPredictiveAnalyticsPlugin' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/../Entity',
                        'prefix' => 'Tmonier\SyliusPredictiveAnalyticsPlugin\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $this->prependDoctrineMigrations($container);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_predictive_analytics';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusPredictiveAnalyticsPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
            'Tmonier\SyliusCompanyAccountsPlugin\Migrations',
            'Tmonier\SyliusCompanyInsightsPlugin\Migrations',
        ];
    }
}
