<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * US-240: Tests the reorder alerts block rendering in customer account.
 *
 * - Product name only, or "product — variant" if variant name differs
 * - Median interval formatted according to locale (56,5 in FR, 56.5 in EN)
 * - Buttons with B2B account theme classes
 */
final class AlertBlockRenderingTest extends WebTestCase
{
    use PredictiveTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_displays_product_name_without_duplicate_when_variant_name_matches(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // Create a product where variant name equals product name (duplicate case)
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Safety Vest']);

        // Force variant name to match product name
        $product = $variant->getProduct();
        self::assertInstanceOf(ProductInterface::class, $product);
        $variant->setName('Safety Vest'); // Same as product
        $this->entityManager()->flush();

        $this->createAlertWithMedian($customer, $variant, 50.0, $company);

        $this->loginShopUser($customer);
        $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        self::assertResponseIsSuccessful();

        // Product name should appear only once (not "Safety Vest - Safety Vest")
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $matches = \substr_count($content, 'Safety Vest');
        self::assertSame(1, $matches, 'Product name should appear only once when variant name matches');
    }

    /**
     * @test
     */
    public function it_displays_product_and_variant_names_when_different(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // Create a product where variant name differs from product name
        $variant = $catalog->product('vest', [$catalog->localeCode() => 'Safety Vest']);

        // Set a different variant name
        $variant->setName('Yellow Class 2');
        $this->entityManager()->flush();

        $this->createAlertWithMedian($customer, $variant, 50.0, $company);

        $this->loginShopUser($customer);
        $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        self::assertResponseIsSuccessful();

        // Both product and variant names should appear
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', 'Safety Vest');
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', 'Yellow Class 2');

        // And they should be separated by " — " (em dash with spaces)
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('Safety Vest — Yellow Class 2', $content);
    }

    /**
     * @test
     */
    public function it_formats_median_interval_with_french_locale(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        $variant = $catalog->product('widget', ['fr_FR' => 'Widget']);

        // Median with decimal: 56.5 days → should display as "56,5" in French
        $this->createAlertWithMedian($customer, $variant, 56.5, $company);

        $this->loginShopUser($customer);
        $this->client->request('GET', '/fr_FR/account/dashboard');

        self::assertResponseIsSuccessful();

        // French locale should use comma as decimal separator
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', '56,5');

        // Should NOT contain dot as separator
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('56.5', $content);
    }

    /**
     * @test
     */
    public function it_formats_median_interval_with_english_locale(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        $variant = $catalog->product('widget', ['en_US' => 'Widget']);

        // Median with decimal: 56.5 days → should display as "56.5" in English
        $this->createAlertWithMedian($customer, $variant, 56.5, $company);

        $this->loginShopUser($customer);
        $this->client->request('GET', '/en_US/account/dashboard');

        self::assertResponseIsSuccessful();

        // English locale should use dot as decimal separator
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', '56.5');
    }

    /**
     * @test
     */
    public function it_uses_correct_css_classes_for_buttons(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $this->createAlertWithMedian($customer, $variant, 50.0, $company);

        $this->loginShopUser($customer);
        $crawler = $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        self::assertResponseIsSuccessful();

        // "Prepare cart" button should have b2b-account__action with --solid modifier
        $prepareButton = $crawler->filter('[data-test="prepare-cart-button"]');
        self::assertCount(1, $prepareButton);
        self::assertTrue(
            $prepareButton->first()->attr('class') === 'b2b-account__action b2b-account__action--solid',
            'Prepare cart button should use b2b-account__action b2b-account__action--solid classes'
        );

        // "Not now" and "Don't suggest again" buttons should have b2b-account__action with --outline modifier
        $dismissButtons = $crawler->filter('[data-test^="dismiss-form"] button');
        self::assertGreaterThanOrEqual(2, \count($dismissButtons));

        foreach ($dismissButtons as $button) {
            $class = $button->getAttribute('class');
            self::assertStringContainsString('b2b-account__action', $class ?? '');
            self::assertStringContainsString('b2b-account__action--outline', $class ?? '');
        }
    }

    private function createAlertWithMedian(CustomerInterface $customer, ProductVariantInterface $variant, float $medianDays, CompanyInterface $company): void
    {
        $now = new \DateTimeImmutable();
        $intervalDays = (int) $medianDays;

        // 3 orders spaced by the median interval
        // Last order positioned to have alert within 7 days
        $catalog = $this->catalog();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify("-{$intervalDays} days")->modify('-56 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify("-{$intervalDays} days")->modify('-28 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
        
        
        $calculator->recalculateForCompany($company);
    }

    private function loginShopUser(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user, 'shop');
    }

    private function shopUrl(string $path): string
    {
        return sprintf('/%s%s', $this->catalog()->localeCode(), $path);
    }
}
