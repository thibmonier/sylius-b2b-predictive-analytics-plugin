<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Provider\ReorderAlertProvider;

final class ReorderAlertProviderTest extends TestCase
{
    public function testNoAlertsForAnonymousVisitor(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        self::assertSame([], $this->provider($security)->getAlerts());
    }

    public function testNoAlertsForShopUserWithoutCustomer(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getCustomer')->willReturn(null);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        self::assertSame([], $this->provider($security)->getAlerts());
    }

    private function provider(Security $security): ReorderAlertProvider
    {
        return new ReorderAlertProvider(
            $security,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ClockInterface::class),
        );
    }
}
