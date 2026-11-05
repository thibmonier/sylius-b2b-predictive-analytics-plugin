<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\DismissedPrediction;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\StoredPrediction;

final class PredictionEntitiesTest extends TestCase
{
    public function testDismissedPredictionExposesItsConstructorValues(): void
    {
        $company = $this->createStub(CompanyInterface::class);
        $variant = $this->createStub(ProductVariantInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $at = new \DateTimeImmutable('2026-11-01 10:00:00');

        $dismissed = new DismissedPrediction($company, $variant, $customer, DismissedPrediction::TYPE_LATER, $at);

        self::assertNull($dismissed->getId());
        self::assertSame($company, $dismissed->getCompany());
        self::assertSame($variant, $dismissed->getVariant());
        self::assertSame($customer, $dismissed->getCustomer());
        self::assertSame('later', $dismissed->getType());
        self::assertSame($at, $dismissed->getDismissedAt());
    }

    public function testStoredPredictionExposesItsConstructorValues(): void
    {
        $company = $this->createStub(CompanyInterface::class);
        $variant = $this->createStub(ProductVariantInterface::class);
        $last = new \DateTimeImmutable('2026-10-01');

        $prediction = new StoredPrediction($company, $variant, 21.5, $last, 4, null, 'Tous les 21,5 jours');

        self::assertNull($prediction->getId());
        self::assertSame($company, $prediction->getCompany());
        self::assertSame($variant, $prediction->getVariant());
        self::assertSame(21.5, $prediction->getMedianIntervalDays());
        self::assertSame($last, $prediction->getLastOrderDate());
        self::assertSame(4, $prediction->getOrderCount());
        self::assertNull($prediction->getReason());
        self::assertSame('Tous les 21,5 jours', $prediction->getExplanation());
    }

    public function testUpdateReplacesThePredictionAndRefreshesCalculationDate(): void
    {
        $prediction = new StoredPrediction(
            $this->createStub(CompanyInterface::class),
            $this->createStub(ProductVariantInterface::class),
            21.5,
            new \DateTimeImmutable('2026-10-01'),
            4,
            null,
            'avant',
        );
        $before = $prediction->getCalculatedAt();
        $newLast = new \DateTimeImmutable('2026-11-02');

        $prediction->update(null, $newLast, 2, 'insufficient_history', 'après');

        self::assertNull($prediction->getMedianIntervalDays());
        self::assertSame($newLast, $prediction->getLastOrderDate());
        self::assertSame(2, $prediction->getOrderCount());
        self::assertSame('insufficient_history', $prediction->getReason());
        self::assertSame('après', $prediction->getExplanation());
        self::assertGreaterThanOrEqual($before, $prediction->getCalculatedAt());
    }
}
