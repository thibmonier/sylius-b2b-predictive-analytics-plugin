<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;

/**
 * Test du masquage d'alerte : « Pas maintenant » et « Ne plus proposer ».
 */
final class DismissPredictionTest extends WebTestCase
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
    public function it_hides_alert_permanently_when_never_show_clicked(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // 3 commandes → échéance dans 4 jours
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify('-80 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify('-52 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
        $calculator->recalculateForCompany($company);

        $this->loginShopUser($customer);

        // Avant masquage : l'alerte est affichée
        $crawler = $this->client->request('GET', $this->shopUrl('/account/dashboard'));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="reorder-alert-item"]', 'L\'alerte doit être affichée avant le masquage');

        // Clic sur « Ne plus proposer »
        $form = $crawler->filter('[data-test="dismiss-form-never"]')->form();
        $this->client->submit($form);

        // Redirection vers le dashboard
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Après masquage : l'alerte ne doit plus être affichée
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="reorder-alert-item"]', 'L\'alerte ne doit plus être affichée après masquage permanent');
    }

    /**
     * @test
     */
    public function it_hides_alert_temporarily_when_not_now_clicked(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // 3 commandes → échéance dans 4 jours
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify('-80 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify('-52 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
        $calculator->recalculateForCompany($company);

        $this->loginShopUser($customer);

        // Avant masquage : l'alerte est affichée
        $crawler = $this->client->request('GET', $this->shopUrl('/account/dashboard'));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="reorder-alert-item"]', 'L\'alerte doit être affichée avant le masquage');

        // Clic sur « Pas maintenant »
        $form = $crawler->filter('[data-test="dismiss-form-later"]')->form();
        $this->client->submit($form);

        // Redirection vers le dashboard
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Après masquage temporaire : l'alerte ne doit plus être affichée
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="reorder-alert-item"]', 'L\'alerte ne doit plus être affichée après masquage temporaire');
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
