<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\MedianQuantityCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\StoredPrediction;

/**
 * Prépare le panier en ajoutant une référence avec la quantité médiane prédite.
 */
final readonly class PrepareCartController
{
    /**
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     */
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private ChannelContextInterface $channelContext,
        private OrderRepositoryInterface $orderRepository,
        private FactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private OrderModifierInterface $orderModifier,
        private UrlGeneratorInterface $urlGenerator,
        private MedianQuantityCalculator $medianQuantityCalculator,
    ) {
    }

    public function __invoke(Request $request, int $variantId): Response
    {
        // Vérification du jeton CSRF
        $csrfToken = $request->request->getString('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('tmonier_predictive_prepare_cart', $csrfToken))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        // Récupération du membre connecté
        $user = $this->security->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedHttpException('User not authenticated.');
        }

        $customer = $user->getCustomer();
        if (null === $customer) {
            throw new AccessDeniedHttpException('Customer not found.');
        }

        $memberRepository = $this->entityManager->getRepository(CompanyMemberInterface::class);
        $member = $memberRepository->findOneBy(['customer' => $customer]);

        if (null === $member || !$member->getCompany() instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface) {
            throw new AccessDeniedHttpException('Member or company not found.');
        }

        $company = $member->getCompany();

        // Récupération de la prédiction
        $predictionRepository = $this->entityManager->getRepository(StoredPrediction::class);
        $prediction = $predictionRepository->findOneBy(['company' => $company, 'variant' => $variantId]);

        if (null === $prediction) {
            throw new NotFoundHttpException('Prediction not found.');
        }

        $variant = $prediction->getVariant();

        // Calcul de la quantité médiane
        $medianQuantity = $this->medianQuantityCalculator->calculate($company, $variant);

        // Récupération du canal actuel
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            throw new NotFoundHttpException('Channel not found.');
        }

        // Récupération ou création du panier
        /** @var OrderInterface|null $cart */
        $cart = $this->orderRepository->findCartByChannel($customer, $channel);
        if (null === $cart) {
            throw new NotFoundHttpException('Cart not found.');
        }

        // Ajout au panier
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);

        // Définir la quantité médiane
        $this->orderItemQuantityModifier->modify($orderItem, $medianQuantity);

        $this->orderModifier->addToOrder($cart, $orderItem);
        $this->entityManager->flush();

        // Redirection vers le panier
        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary'));
    }
}
