<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\AlertCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\DismissedPrediction;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\StoredPrediction;

/**
 * Fournit les alertes de réassort pour le membre connecté :
 * prédictions avec échéance dans les 7 jours, variantes actives uniquement, cloisonnées par entreprise.
 */
final readonly class ReorderAlertProvider
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<StoredPrediction>
     */
    public function getAlerts(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof ShopUserInterface) {
            return [];
        }

        $customer = $user->getCustomer();
        if (!$customer instanceof \Sylius\Component\Customer\Model\CustomerInterface) {
            return [];
        }

        // Récupération du membre de l'entreprise
        $memberRepository = $this->entityManager->getRepository(CompanyMemberInterface::class);
        $member = $memberRepository->findOneBy(['customer' => $customer]);

        if (null === $member || !$member->getCompany() instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface) {
            return [];
        }

        $company = $member->getCompany();

        $now = $this->clock->now();

        // Requête DQL pour récupérer les prédictions avec médiane et dernière commande
        // Exclure les alertes masquées par le client courant
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(StoredPrediction::class, 'p')
            ->join('p.variant', 'v')
            ->join('v.product', 'product')
            ->leftJoin(
                DismissedPrediction::class,
                'd',
                'WITH',
                'd.company = p.company AND d.variant = p.variant AND d.customer = :customer',
            )
            ->where('p.company = :company')
            ->andWhere('p.medianIntervalDays IS NOT NULL')
            ->andWhere('p.lastOrderDate IS NOT NULL')
            ->andWhere('v.enabled = true')
            ->andWhere('product.enabled = true')
            ->andWhere('d.id IS NULL')
            ->setParameter('company', $company)
            ->setParameter('customer', $customer);

        /** @var list<StoredPrediction> $predictions */
        $predictions = $qb->getQuery()->getResult();

        // Filtrer par la règle d'alerte (délégation à AlertCalculator)
        $alerts = [];
        foreach ($predictions as $prediction) {
            $medianIntervalDays = $prediction->getMedianIntervalDays();
            $lastOrderDate = $prediction->getLastOrderDate();

            if (null === $medianIntervalDays || !$lastOrderDate instanceof \DateTimeImmutable) {
                continue;
            }

            if (AlertCalculator::shouldAlert($now, $lastOrderDate, $medianIntervalDays)) {
                $alerts[] = $prediction;
            }
        }

        return $alerts;
    }
}
