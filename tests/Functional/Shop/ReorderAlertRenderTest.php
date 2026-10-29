<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Shop;

use Sylius\Bundle\CoreBundle\Test\Client\TestClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;

/**
 * Le bloc d'alertes de réassort s'affiche dans « Mon compte » si au moins une prédiction
 * a une échéance dans les 7 jours. Le bloc ne s'affiche pas (sans erreur) si aucune alerte
 * ou si le client n'a pas d'entreprise.
 *
 * @covers \Tmonier\SyliusPredictiveAnalyticsPlugin\Provider\ReorderAlertProvider
 */
final class ReorderAlertRenderTest extends WebTestCase
{
    use PredictiveTestTrait;

    private TestClient $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
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
        $member = $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours (médiane = 28 jours)
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], '2025-01-01 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], '2025-01-29 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], '2025-02-26 10:00:00');
        // Dernière commande : 2025-02-26
        // Prochaine échéance attendue : 2025-02-26 + 28 jours = 2025-03-26
        // Si on est le 2025-03-22, l'échéance est dans 4 jours (< 7 jours)

        // Recalcul de la prédiction
        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($company);

        // Connexion en tant que membre
        $this->client->loginUser($member->getShopUser());

        // Visite de « Mon compte » le 2025-03-22 (4 jours avant l'échéance)
        $crawler = $this->client->request('GET', '/en_US/account/dashboard', [], [], [
            'HTTP_X_FAKE_NOW' => '2025-03-22T10:00:00+00:00',
        ]);

        // Le bloc d'alertes doit être affiché
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes doit être affiché');
        self::assertSelectorTextContains('[data-test="reorder-alerts"]', 'À réassortir bientôt');

        // La référence doit être affichée avec son explication
        self::assertSelectorExists('[data-test="reorder-alert-item"]');
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', 'commandée 3 fois');
        self::assertSelectorTextContains('[data-test="reorder-alert-item"]', '28 jours en médiane');

        // Le bouton « Préparer le panier » doit être affiché
        self::assertSelectorExists('[data-test="prepare-cart-button"]');
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
        $member = $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], '2025-01-01 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], '2025-01-29 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], '2025-02-26 10:00:00');
        // Dernière commande : 2025-02-26
        // Prochaine échéance attendue : 2025-03-26
        // Si on est le 2025-03-01, l'échéance est dans 25 jours (> 7 jours)

        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($company);

        $this->client->loginUser($member->getShopUser());

        // Visite de « Mon compte » le 2025-03-01 (25 jours avant l'échéance)
        $crawler = $this->client->request('GET', '/en_US/account/dashboard', [], [], [
            'HTTP_X_FAKE_NOW' => '2025-03-01T10:00:00+00:00',
        ]);

        // Le bloc d'alertes ne doit PAS être affiché
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

        $this->client->loginUser($customer->getUser());

        // Visite de « Mon compte »
        $crawler = $this->client->request('GET', '/en_US/account/dashboard');

        // La page doit se charger sans erreur
        self::assertResponseIsSuccessful();

        // Le bloc d'alertes ne doit PAS être affiché (client sans entreprise)
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes ne doit pas être affiché pour un client sans entreprise');
    }

    /**
     * @test
     */
    public function it_renders_without_error_when_company_has_no_predictions(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $customer = $catalog->customer('claire');
        $member = $catalog->member($company, $customer);

        // Aucune commande, donc aucune prédiction

        $this->client->loginUser($member->getShopUser());

        // Visite de « Mon compte »
        $crawler = $this->client->request('GET', '/en_US/account/dashboard');

        // La page doit se charger sans erreur
        self::assertResponseIsSuccessful();

        // Le bloc d'alertes ne doit PAS être affiché (aucune prédiction)
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes ne doit pas être affiché si aucune prédiction');
    }

    /**
     * @test
     */
    public function it_filters_dismissed_alerts(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $member = $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], '2025-01-01 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], '2025-01-29 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], '2025-02-26 10:00:00');

        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($company);

        // Masquage de l'alerte (« Ne plus proposer »)
        $dismissedAlertRepository = self::getContainer()->get('tmonier_predictive.repository.dismissed_alert');
        $dismissedAlert = $dismissedAlertRepository->createNew();
        $dismissedAlert->setCompany($company);
        $dismissedAlert->setVariant($variant);
        $dismissedAlert->setShopUser($member->getShopUser());
        $dismissedAlert->setType('never_show');
        $this->entityManager()->persist($dismissedAlert);
        $this->entityManager()->flush();

        $this->client->loginUser($member->getShopUser());

        // Visite de « Mon compte » le 2025-03-22 (4 jours avant l'échéance)
        $crawler = $this->client->request('GET', '/en_US/account/dashboard', [], [], [
            'HTTP_X_FAKE_NOW' => '2025-03-22T10:00:00+00:00',
        ]);

        // Le bloc d'alertes ne doit PAS être affiché (alerte masquée)
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes ne doit pas être affiché si l\'alerte est masquée');
    }

    /**
     * @test
     */
    public function it_filters_disabled_variants(): void
    {
        $catalog = $this->catalog();
        $company = $catalog->company('Acme');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $customer = $catalog->customer('claire');
        $member = $catalog->member($company, $customer);

        // 3 commandes complétées espacées de 28 jours
        $catalog->orderOfLines($customer, [[$variant, 1, 10000]], '2025-01-01 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 12000]], '2025-01-29 10:00:00');
        $catalog->orderOfLines($customer, [[$variant, 1, 11000]], '2025-02-26 10:00:00');

        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($company);

        // Désactivation de la référence
        $variant->setEnabled(false);
        $this->entityManager()->flush();

        $this->client->loginUser($member->getShopUser());

        // Visite de « Mon compte » le 2025-03-22 (4 jours avant l'échéance)
        $crawler = $this->client->request('GET', '/en_US/account/dashboard', [], [], [
            'HTTP_X_FAKE_NOW' => '2025-03-22T10:00:00+00:00',
        ]);

        // Le bloc d'alertes ne doit PAS être affiché (référence désactivée)
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Le bloc d\'alertes ne doit pas être affiché si la référence est désactivée');
    }

    /**
     * @test
     */
    public function it_cloisonizes_alerts_by_company(): void
    {
        $catalog = $this->catalog();

        // Entreprise Acme
        $acme = $catalog->company('Acme Industries');
        $variant = $catalog->product('widget', [$catalog->localeCode() => 'Widget']);
        $claire = $catalog->customer('claire');
        $catalog->member($acme, $claire);

        $catalog->orderOfLines($claire, [[$variant, 1, 10000]], '2025-01-01 10:00:00');
        $catalog->orderOfLines($claire, [[$variant, 1, 12000]], '2025-01-29 10:00:00');
        $catalog->orderOfLines($claire, [[$variant, 1, 11000]], '2025-02-26 10:00:00');

        // Entreprise BuildCo
        $buildco = $catalog->company('BuildCo LLC');
        $bob = $catalog->customer('bob');
        $bobMember = $catalog->member($buildco, $bob);

        // BuildCo n'a AUCUNE commande

        $calculator = self::getContainer()->get('Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface');
        $calculator->recalculateForCompany($acme);
        $calculator->recalculateForCompany($buildco);

        // Connexion en tant que Bob (BuildCo)
        $this->client->loginUser($bob->getUser());

        // Visite de « Mon compte » le 2025-03-22
        $crawler = $this->client->request('GET', '/en_US/account/dashboard', [], [], [
            'HTTP_X_FAKE_NOW' => '2025-03-22T10:00:00+00:00',
        ]);

        // Le bloc d'alertes ne doit PAS être affiché pour Bob (il ne voit que les prédictions de BuildCo)
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="reorder-alerts"]', 'Bob ne doit voir que les prédictions de BuildCo, pas celles d\'Acme');
    }
}
