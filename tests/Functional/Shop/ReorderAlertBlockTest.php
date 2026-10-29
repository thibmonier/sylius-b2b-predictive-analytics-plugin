<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;

/**
 * Le bloc d'alertes de réassort s'affiche dans « Mon compte » si au moins une prédiction
 * a une échéance dans les 7 jours.
 */
final class ReorderAlertBlockTest extends WebTestCase
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
    public function it_shows_alert_block_when_reorder_expected_within_7_days(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours (médiane = 28 jours)
        // Dernière commande il y a 24 jours → échéance dans 4 jours (28 - 24 = 4)
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify('-80 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify('-52 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        // Recalcul de la prédiction
        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($company);

        // Connexion en tant que membre
        $this->loginShopUser($customer);

        // Visite de « Mon compte »
        $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        // Le bloc d'alertes doit être affiché avec son contenu
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes doit être affiché');
        self::assertSelectorTextContains('[data-test="reorder-alerts"]', 'À réassortir bientôt');

        // La référence doit être affichée
        self::assertSelectorExists('[data-test="reorder-alert-item"]', 'Une alerte doit être affichée');

        // L'explication doit contenir le rythme (3 fois, 28 jours en médiane)
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', 'commandée 3 fois');
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', '28 jours en médiane');

        // Le bouton « Préparer le panier » doit être affiché
        self::assertSelectorExists('[data-test="prepare-cart-button"]', 'Le bouton "Préparer le panier" doit être affiché');
    }

    /**
     * @test
     */
    public function it_does_not_show_alert_block_when_no_reorder_expected_within_7_days(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours
        // Dernière commande il y a 50 jours → échéance dans -22 jours (PASSÉE)
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify('-106 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify('-78 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-50 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($company);

        $this->loginShopUser($customer);

        // Visite de « Mon compte » le 2025-03-01 (25 jours avant l'échéance)
        $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        // Le bloc d'alertes ne doit PAS être affiché (aucune échéance dans les 7 jours)
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes ne doit pas être affiché si aucune échéance dans les 7 jours');
    }

    /**
     * @test
     */
    public function it_renders_without_error_when_customer_has_no_company(): void
    {
        $catalog = $this->catalog();
        // Client sans entreprise (B2C)
        $customer = $catalog->customer('john');

        $this->loginShopUser($customer);

        // Visite de « Mon compte »
        $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        // La page doit se charger sans erreur
        self::assertResponseIsSuccessful();

        // Le bloc d'alertes ne doit PAS être affiché (client sans entreprise)
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes ne doit pas être affiché pour un client sans entreprise');
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
