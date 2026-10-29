<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
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
        // Dernière commande il y a 2 jours → échéance dans 26 jours (LOINTAINE, > 7 jours)
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify('-58 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify('-30 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-2 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
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
    public function it_shows_alert_block_when_reorder_is_late(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours (médiane = 28 jours)
        // Dernière commande il y a 30 jours → échéance il y a 2 jours (EN RETARD)
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], $now->modify('-86 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], $now->modify('-58 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], $now->modify('-30 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
        $calculator->recalculateForCompany($company);

        $this->loginShopUser($customer);

        // Visite de « Mon compte »
        $this->client->request('GET', $this->shopUrl('/account/dashboard'));

        // Le bloc d'alertes doit être affiché (référence EN RETARD de 2 jours)
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes doit être affiché même si la référence est en retard');
        self::assertSelectorExists('[data-test="reorder-alert-item"]', 'Une alerte en retard doit être affichée');
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

    /**
     * @test
     */
    public function it_prepares_cart_with_median_quantity_when_button_clicked(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $catalog->member($company, $customer);

        // 3 commandes avec quantités différentes : 2, 5, 3 → médiane = 3
        $now = new \DateTimeImmutable();
        $catalog->orderOfLines($customer, [[$variant, 2, 10000]], $now->modify('-80 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 5, 12000]], $now->modify('-52 days')->format('Y-m-d H:i:s'));
        $catalog->orderOfLines($customer, [[$variant, 3, 11000]], $now->modify('-24 days')->format('Y-m-d H:i:s'));

        /** @var \Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface $calculator */
        $calculator = self::getContainer()->get(\Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface::class);
        $calculator->recalculateForCompany($company);

        $this->loginShopUser($customer);

        // GET du tableau de bord
        $crawler = $this->client->request('GET', $this->shopUrl('/account/dashboard'));
        self::assertResponseIsSuccessful();

        // Le bouton "Préparer le panier" doit être affiché
        self::assertSelectorExists('[data-test="prepare-cart-button"]', 'Le bouton "Préparer le panier" doit être affiché');

        // Soumission du formulaire via le crawler (jeton CSRF inclus)
        $form = $crawler->filter('[data-test="prepare-cart-form"]')->form();
        $this->client->submit($form);

        // La réponse doit être une redirection vers le panier (URL générée de sylius_shop_cart_summary)
        $router = self::getContainer()->get('router');
        assert($router instanceof UrlGeneratorInterface);
        $cartUrl = $router->generate('sylius_shop_cart_summary', ['_locale' => $catalog->localeCode()]);
        self::assertResponseRedirects($cartUrl);

        // Vérifier le résultat MÉTIER en base : le panier du client contient la variante avec la quantité médiane
        $orderRepository = self::getContainer()->get('sylius.repository.order');
        assert($orderRepository instanceof OrderRepositoryInterface);
        $cart = $orderRepository->findCartByChannel($customer, $catalog->channel());
        self::assertNotNull($cart, 'Le panier doit exister');

        $items = $cart->getItems();
        self::assertCount(1, $items, 'Le panier doit contenir exactement 1 ligne');

        $item = $items->first();
        self::assertSame($variant, $item->getVariant(), 'La variante doit être Widget');
        self::assertSame(3, $item->getQuantity(), 'La quantité doit être la médiane (3)');
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
