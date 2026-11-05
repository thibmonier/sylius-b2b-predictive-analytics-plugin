<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\DenyCompanyReadChecker;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

trait AttritionAdminTestTrait
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
        DenyCompanyReadChecker::$denyCompanyRead = false;
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    private function loginAdmin(string $locale = 'en_US'): void
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $admin->setLocaleCode($locale);
        $this->entityManager()->flush();
        $this->client->loginUser($admin, 'admin');
    }

    /**
     * Une entreprise qui commande tous les `$interval` jours : `$orders` commandes, la dernière il y a
     * `$daysSinceLast` jours, après une première commande ancienne (historique de plus de 6 mois).
     */
    private function companyOrderingEvery(string $name, int $interval, int $orders, int $daysSinceLast, bool $longHistory = true): CompanyInterface
    {
        $catalog = $this->catalog();
        $company = $catalog->company($name);
        $customer = $catalog->customer(strtolower($name) . '-buyer');
        $catalog->member($company, $customer);
        $variant = $catalog->product(strtolower($name) . '-widget', [$catalog->localeCode() => 'Widget']);

        $now = new \DateTimeImmutable();
        if ($longHistory) {
            $this->placeOrder($customer, $variant, $now->modify('-400 days'));
        }
        for ($i = $orders - 1; $i >= 0; --$i) {
            $this->placeOrder($customer, $variant, $now->modify(sprintf('-%d days', $daysSinceLast + $i * $interval)));
        }

        return $company;
    }

    private function placeOrder(object $customer, object $variant, \DateTimeImmutable $date): void
    {
        $this->catalog()->orderOfLines($customer, [[$variant, 1, 10000]], $date->format('Y-m-d H:i:s'));
    }

    private function companyEditUrl(CompanyInterface $company): string
    {
        return sprintf('/admin/companies/%d/edit', (int) $company->getId());
    }
}
