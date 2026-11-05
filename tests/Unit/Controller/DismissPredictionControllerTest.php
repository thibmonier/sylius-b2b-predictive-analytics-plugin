<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Controller\Shop\DismissPredictionController;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\DismissedPrediction;

/** Branches de refus du masquage d'alerte, non atteignables par le routeur (type) ou par un parcours web simple. */
final class DismissPredictionControllerTest extends TestCase
{
    public function testRejectsInvalidCsrfToken(): void
    {
        $controller = $this->controller(csrfValid: false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $controller(new Request(), 1, DismissedPrediction::TYPE_NEVER);
    }

    public function testRejectsUnknownDismissType(): void
    {
        $controller = $this->controller();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid dismiss type.');

        $controller(new Request(), 1, 'forever');
    }

    public function testRejectsAnonymousUser(): void
    {
        $controller = $this->controller(user: null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('User not authenticated.');

        $controller(new Request(), 1, DismissedPrediction::TYPE_LATER);
    }

    public function testRejectsUserWithoutCustomer(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getCustomer')->willReturn(null);
        $controller = $this->controller(user: $user);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Customer not found.');

        $controller(new Request(), 1, DismissedPrediction::TYPE_LATER);
    }

    public function testRejectsCustomerWithoutCompanyMember(): void
    {
        $controller = $this->controller(user: $this->userWithCustomer(), member: null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Member or company not found.');

        $controller(new Request(), 1, DismissedPrediction::TYPE_LATER);
    }

    public function testRejectsUnknownVariant(): void
    {
        $member = $this->createStub(CompanyMemberInterface::class);
        $member->method('getCompany')->willReturn($this->createStub(CompanyInterface::class));
        $controller = $this->controller(user: $this->userWithCustomer(), member: $member, variant: null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Variant not found.');

        $controller(new Request(), 999, DismissedPrediction::TYPE_NEVER);
    }

    private function userWithCustomer(): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getCustomer')->willReturn($this->createStub(CustomerInterface::class));

        return $user;
    }

    private function controller(
        bool $csrfValid = true,
        ?ShopUserInterface $user = null,
        ?CompanyMemberInterface $member = null,
        ?ProductVariantInterface $variant = null,
    ): DismissPredictionController {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $memberRepository = $this->createStub(EntityRepository::class);
        $memberRepository->method('findOneBy')->willReturn($member);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($memberRepository);

        $variantRepository = $this->createStub(ProductVariantRepositoryInterface::class);
        $variantRepository->method('find')->willReturn($variant);

        return new DismissPredictionController(
            $security,
            $entityManager,
            $csrf,
            $variantRepository,
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
        );
    }
}
