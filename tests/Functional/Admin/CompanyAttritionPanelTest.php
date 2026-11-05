<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\DenyCompanyReadChecker;

/**
 * US-220 : le statut d'attrition, sa raison et la mention « indicatif » sur la fiche entreprise de l'admin.
 */
final class CompanyAttritionPanelTest extends WebTestCase
{
    use AttritionAdminTestTrait;

    public function testAtRiskCompanyShowsStatusAndReasonInEnglish(): void
    {
        $company = $this->companyOrderingEvery('Slowing', 21, 5, 47);
        $this->loginAdmin();

        $this->client->request('GET', $this->companyEditUrl($company));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="attrition-status"]', 'At risk');
        self::assertSelectorTextContains('[data-test="attrition-reason"]', 'orders every 21 days, nothing for 47 days');
        self::assertSelectorExists('[data-test="attrition-indicative"]');
    }

    public function testAtRiskCompanyShowsStatusAndReasonInFrench(): void
    {
        $company = $this->companyOrderingEvery('Ralentie', 21, 5, 47);
        $this->loginAdmin('fr_FR');

        $this->client->request('GET', $this->companyEditUrl($company));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="attrition-status"]', 'À risque');
        self::assertSelectorTextContains('[data-test="attrition-reason"]', 'commande tous les 21 jours, rien depuis 47 jours');
        self::assertSelectorTextContains('[data-test="attrition-indicative"]', 'Indicatif');
    }

    public function testCriticalCompanyShowsTheCriticalStatus(): void
    {
        $company = $this->companyOrderingEvery('Gone', 21, 5, 70);
        $this->loginAdmin();

        $this->client->request('GET', $this->companyEditUrl($company));

        self::assertSelectorTextContains('[data-test="attrition-status"]', 'Critical');
        self::assertSelectorTextContains('[data-test="attrition-reason"]', 'orders every 21 days, nothing for 70 days');
    }

    public function testCompanyInItsRhythmIsActiveWithItsUsualRhythm(): void
    {
        $company = $this->companyOrderingEvery('Steady', 21, 5, 10);
        $this->loginAdmin();

        $this->client->request('GET', $this->companyEditUrl($company));

        self::assertSelectorTextContains('[data-test="attrition-status"]', 'Active');
        self::assertSelectorTextContains('[data-test="attrition-reason"]', 'orders every 21 days');
    }

    public function testCompanyWithFewerThanFourOrdersShowsInsufficientHistoryWithoutRhythm(): void
    {
        $company = $this->companyOrderingEvery('Starter', 21, 2, 10, false);
        $this->loginAdmin();

        $this->client->request('GET', $this->companyEditUrl($company));

        self::assertSelectorTextContains('[data-test="attrition-status"]', 'Insufficient history');
        self::assertSelectorNotExists('[data-test="attrition-reason"]');
    }

    public function testCompanyWithLessThanSixMonthsOfHistoryShowsNoAlertStatus(): void
    {
        $established = $this->companyOrderingEvery('Established', 10, 5, 5);
        $recent = $this->companyOrderingEvery('Newcomer', 10, 5, 5, false);
        $this->loginAdmin();

        $this->client->request('GET', $this->companyEditUrl($established));
        self::assertSelectorExists('[data-test="attrition-status"]');

        $this->client->request('GET', $this->companyEditUrl($recent));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="attrition-status"]');
    }

    public function testScoreIsHiddenWithoutThePermissionToReadCompanies(): void
    {
        $company = $this->companyOrderingEvery('Hidden', 21, 5, 47);
        $this->loginAdmin();

        $this->client->request('GET', $this->companyEditUrl($company));
        self::assertSelectorExists('[data-test="attrition-status"]');

        DenyCompanyReadChecker::$denyCompanyRead = true;
        $this->client->request('GET', $this->companyEditUrl($company));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="attrition-status"]');
        self::assertSelectorNotExists('[data-test="attrition-reason"]');
    }
}
