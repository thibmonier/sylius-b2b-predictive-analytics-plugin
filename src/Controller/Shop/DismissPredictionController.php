<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\DismissedPrediction;

/**
 * Masque une alerte de réassort.
 */
final readonly class DismissPredictionController
{
    /**
     * @param ProductVariantRepositoryInterface<ProductVariantInterface> $variantRepository
     */
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private ProductVariantRepositoryInterface $variantRepository,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, int $variantId, string $type): RedirectResponse
    {
        // Vérification du jeton CSRF
        $csrfToken = $request->request->getString('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('tmonier_predictive_dismiss', $csrfToken))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        // Vérification du type
        if (!\in_array($type, [DismissedPrediction::TYPE_NEVER, DismissedPrediction::TYPE_LATER], true)) {
            throw new NotFoundHttpException('Invalid dismiss type.');
        }

        // Récupération du membre connecté
        $user = $this->security->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedHttpException('User not authenticated.');
        }

        $customer = $user->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            throw new AccessDeniedHttpException('Customer not found.');
        }

        $memberRepository = $this->entityManager->getRepository(CompanyMemberInterface::class);
        $member = $memberRepository->findOneBy(['customer' => $customer]);

        if (null === $member || !$member->getCompany() instanceof CompanyInterface) {
            throw new AccessDeniedHttpException('Member or company not found.');
        }

        $company = $member->getCompany();

        // Récupération de la variante
        $variant = $this->variantRepository->find($variantId);
        if (!$variant instanceof ProductVariantInterface) {
            throw new NotFoundHttpException('Variant not found.');
        }

        // Création ou mise à jour du masquage
        $dismissedRepository = $this->entityManager->getRepository(DismissedPrediction::class);
        $dismissed = $dismissedRepository->findOneBy([
            'company' => $company,
            'variant' => $variant,
            'customer' => $customer,
        ]);

        if (null === $dismissed) {
            $dismissed = new DismissedPrediction(
                $company,
                $variant,
                $customer,
                $type,
                $this->clock->now(),
            );
            $this->entityManager->persist($dismissed);
        }

        $this->entityManager->flush();

        // Redirection vers le dashboard
        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_account_dashboard'));
    }
}
