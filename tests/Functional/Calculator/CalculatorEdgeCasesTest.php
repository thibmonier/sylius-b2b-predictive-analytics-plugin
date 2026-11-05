<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Calculator;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\MedianQuantityCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;

/** Cas limites des calculateurs : entités non persistées et nombre pair de commandes. */
final class CalculatorEdgeCasesTest extends KernelTestCase
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

    public function testMedianQuantityIsAveragedWhenTheNumberOfOrdersIsEven(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 2, 10000]], $now->modify('-40 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 5, 10000]], $now->modify('-20 days')->format('Y-m-d H:i:s'));

        $median = $this->medianCalculator()->calculate($company, $variant);

        self::assertSame(4, $median, 'Moyenne de 2 et 5 = 3,5 arrondi à 4');
    }

    public function testMedianQuantityDefaultsToOneForACompanyWithoutId(): void
    {
        $company = $this->createStub(CompanyInterface::class);
        $company->method('getId')->willReturn(null);
        $variant = $this->createStub(ProductVariantInterface::class);
        $variant->method('getId')->willReturn(12);

        $median = $this->medianCalculator()->calculate($company, $variant);

        self::assertSame(1, $median);
    }

    public function testPredictionRequiresACompanyWithAnId(): void
    {
        $company = $this->createStub(CompanyInterface::class);
        $company->method('getId')->willReturn(null);
        $variant = $this->createStub(ProductVariantInterface::class);
        $variant->method('getId')->willReturn(12);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Company must have an ID');

        $this->calculator()->calculate($variant, $company);
    }

    public function testPredictionRequiresAVariantWithANumericId(): void
    {
        $company = $this->createStub(CompanyInterface::class);
        $company->method('getId')->willReturn(7);
        $variant = $this->createStub(ProductVariantInterface::class);
        $variant->method('getId')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ProductVariant must have a numeric ID');

        $this->calculator()->calculate($variant, $company);
    }

    public function testRecalculationRequiresACompanyWithAnId(): void
    {
        $company = $this->createStub(CompanyInterface::class);
        $company->method('getId')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Company must have an ID');

        $this->calculator()->recalculateForCompany($company);
    }

    private function calculator(): PredictiveReorderCalculatorInterface
    {
        $calculator = self::getContainer()->get(PredictiveReorderCalculatorInterface::class);
        self::assertInstanceOf(PredictiveReorderCalculatorInterface::class, $calculator);

        return $calculator;
    }

    private function medianCalculator(): MedianQuantityCalculator
    {
        $calculator = self::getContainer()->get(MedianQuantityCalculator::class);
        self::assertInstanceOf(MedianQuantityCalculator::class, $calculator);

        return $calculator;
    }
}
