<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private CartContextInterface $cartContext,
        private ChannelRepositoryInterface $channelRepository,
        private FactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private OrderModifierInterface $orderModifier,
        private UrlGeneratorInterface $urlGenerator,
        private MedianQuantityCalculator $medianQuantityCalculator,
    ) {
    }

    public function __invoke(Request $request, int $variantId): \Symfony\Component\HttpFoundation\RedirectResponse
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
        if (!$customer instanceof \Sylius\Component\Customer\Model\CustomerInterface) {
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

        // Récupération ou création du panier (le contexte crée un panier si nécessaire)
        $cart = $this->cartContext->getCart();

        if (!$cart instanceof OrderInterface) {
            throw new NotFoundHttpException('Cart not found.');
        }

        // Persister le panier s'il est nouveau, avec un canal géré (le canal du contexte peut être détaché)
        if (null === $cart->getId()) {
            $managedChannel = $this->channelRepository->findOneByCode($cart->getChannel()->getCode());
            if ($managedChannel instanceof ChannelInterface) {
                $cart->setChannel($managedChannel);
            }

            $this->entityManager->persist($cart);
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
