<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Resource\Metadata\Metadata;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Security\CompanyReadPermission;

/**
 * US-220 : la lecture des entreprises passe par le contrôle d'autorisation des ressources de Sylius.
 */
final class CompanyReadPermissionTest extends TestCase
{
    public function testItAsksTheResourceAuthorizationCheckerForTheShowPermissionOfTheCompany(): void
    {
        $asked = [];
        $permission = $this->permission(static function (string $name) use (&$asked): bool {
            $asked[] = $name;

            return true;
        });

        $permission->isGranted();

        self::assertSame(['tmonier_company_accounts.company.show'], $asked);
    }

    public function testItIsGrantedWhenTheCheckerGrants(): void
    {
        self::assertTrue($this->permission(static fn (): bool => true)->isGranted());
    }

    public function testItIsDeniedWhenTheCheckerDenies(): void
    {
        self::assertFalse($this->permission(static fn (): bool => false)->isGranted());
    }

    /**
     * @param callable(string): bool $decision
     */
    private function permission(callable $decision): CompanyReadPermission
    {
        $checker = new class($decision) implements AuthorizationCheckerInterface {
            /** @param callable(string): bool $decision */
            public function __construct(private $decision)
            {
            }

            public function isGranted(RequestConfiguration $configuration, string $permission): bool
            {
                return ($this->decision)($permission);
            }
        };

        $registry = $this->createStub(RegistryInterface::class);
        $registry->method('get')->willReturn(Metadata::fromAliasAndConfiguration('tmonier_company_accounts.company', ['driver' => 'doctrine/orm']));

        $requests = new RequestStack();
        $requests->push(new Request());

        return new CompanyReadPermission($checker, $registry, $requests);
    }
}
