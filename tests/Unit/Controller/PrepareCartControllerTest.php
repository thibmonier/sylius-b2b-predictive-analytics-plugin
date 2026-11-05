<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Approval\PendingOrderConditionInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\MedianQuantityCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Controller\Shop\PrepareCartController;

/** Branches de refus de la préparation de panier. */
final class PrepareCartControllerTest extends TestCase
{
    public function testRejectsInvalidCsrfToken(): void
    {
        $controller = $this->controller(csrfValid: false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $controller(new Request(), 1);
    }

    public function testRejectsAnonymousUser(): void
    {
        $controller = $this->controller();

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('User not authenticated.');

        $controller(new Request(), 1);
    }

    public function testRejectsUserWithoutCustomer(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getCustomer')->willReturn(null);
        $controller = $this->controller(user: $user);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Customer not found.');

        $controller(new Request(), 1);
    }

    public function testRejectsCustomerWithoutCompanyMember(): void
    {
        $controller = $this->controller(user: $this->userWithCustomer());

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Member or company not found.');

        $controller(new Request(), 1);
    }

    public function testRejectsVariantWithoutStoredPrediction(): void
    {
        $member = $this->createStub(CompanyMemberInterface::class);
        $member->method('getCompany')->willReturn($this->createStub(CompanyInterface::class));
        $controller = $this->controller(user: $this->userWithCustomer(), member: $member);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Prediction not found.');

        $controller(new Request(), 42);
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
    ): PrepareCartController {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        // Premier appel : membre ; second appel : prédiction (toujours absente)
        $memberRepository = $this->createStub(EntityRepository::class);
        $memberRepository->method('findOneBy')->willReturn($member);
        $predictionRepository = $this->createStub(EntityRepository::class);
        $predictionRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnOnConsecutiveCalls($memberRepository, $predictionRepository);

        return new PrepareCartController(
            $security,
            $entityManager,
            $csrf,
            $this->createStub(CartContextInterface::class),
            $this->createStub(ChannelRepositoryInterface::class),
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(OrderItemQuantityModifierInterface::class),
            $this->createStub(OrderModifierInterface::class),
            $this->createStub(UrlGeneratorInterface::class),
            new MedianQuantityCalculator(
                $this->createStub(Connection::class),
                new CountedOrderSql($this->createStub(PendingOrderConditionInterface::class)),
            ),
        );
    }
}
