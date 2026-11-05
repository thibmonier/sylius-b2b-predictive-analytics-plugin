<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * US-220 : filtre « à risque / critique / actif » dans la liste des entreprises de l'admin.
 */
final class CompanyAttritionFilterTest extends WebTestCase
{
    use AttritionAdminTestTrait;

    private CompanyInterface $atRisk;

    private CompanyInterface $critical;

    private CompanyInterface $active;

    private CompanyInterface $insufficient;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->beginTestTransaction();

        $this->atRisk = $this->companyOrderingEvery('Slowing', 21, 5, 47);
        $this->critical = $this->companyOrderingEvery('Gone', 21, 5, 70);
        $this->active = $this->companyOrderingEvery('Steady', 21, 5, 10);
        $this->insufficient = $this->companyOrderingEvery('Starter', 21, 2, 10, false);
        $this->loginAdmin();
    }

    public function testFilteringOnAtRiskListsOnlyTheAtRiskCompanies(): void
    {
        $listed = $this->listedCodes('at_risk');

        self::assertContains($this->atRisk->getCode(), $listed);
        self::assertNotContains($this->critical->getCode(), $listed);
        self::assertNotContains($this->active->getCode(), $listed);
        self::assertNotContains($this->insufficient->getCode(), $listed);
    }

    public function testFilteringOnCriticalListsOnlyTheCriticalCompanies(): void
    {
        $listed = $this->listedCodes('critical');

        self::assertContains($this->critical->getCode(), $listed);
        self::assertNotContains($this->atRisk->getCode(), $listed);
        self::assertNotContains($this->active->getCode(), $listed);
    }

    public function testFilteringOnActiveListsOnlyTheActiveCompanies(): void
    {
        $listed = $this->listedCodes('active');

        self::assertContains($this->active->getCode(), $listed);
        self::assertNotContains($this->atRisk->getCode(), $listed);
        self::assertNotContains($this->critical->getCode(), $listed);
        self::assertNotContains($this->insufficient->getCode(), $listed);
    }

    public function testWithoutFilterEveryCompanyIsListed(): void
    {
        $listed = $this->listedCodes(null);

        foreach ([$this->atRisk, $this->critical, $this->active, $this->insufficient] as $company) {
            self::assertContains($company->getCode(), $listed);
        }
    }

    public function testTheGridOffersTheAttritionFilter(): void
    {
        $this->client->request('GET', '/admin/companies/');

        self::assertSelectorExists('select[name="criteria[attrition]"]');
    }

    /**
     * @return list<string> les codes des entreprises de la liste
     */
    private function listedCodes(?string $status): array
    {
        $query = null === $status ? '' : '?criteria[attrition]=' . $status . '&limit=100';
        $crawler = $this->client->request('GET', '/admin/companies/' . $query);
        self::assertResponseIsSuccessful();

        $codes = [];
        foreach ([$this->atRisk, $this->critical, $this->active, $this->insufficient] as $company) {
            if (str_contains($crawler->filter('table')->text(), (string) $company->getCode())) {
                $codes[] = (string) $company->getCode();
            }
        }

        return $codes;
    }
}
