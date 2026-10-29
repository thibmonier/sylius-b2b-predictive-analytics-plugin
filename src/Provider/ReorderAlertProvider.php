<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
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
        if (null === $customer) {
            return [];
        }

        // Récupération du membre de l'entreprise
        $memberRepository = $this->entityManager->getRepository(CompanyMemberInterface::class);
        $member = $memberRepository->findOneBy(['customer' => $customer]);

        if (null === $member || !$member->getCompany() instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface) {
            return [];
        }

        $company = $member->getCompany();

        // Date limite : dans 7 jours
        $now = $this->clock->now();
        $sevenDaysFromNow = $now->modify('+7 days');

        // Requête DQL pour récupérer les prédictions avec échéance ≤ 7 jours
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(StoredPrediction::class, 'p')
            ->join('p.variant', 'v')
            ->join('v.product', 'product')
            ->where('p.company = :company')
            ->andWhere('p.medianIntervalDays IS NOT NULL')
            ->andWhere('p.lastOrderDate IS NOT NULL')
            ->andWhere('v.enabled = true')
            ->andWhere('product.enabled = true')
            ->setParameter('company', $company);

        /** @var list<StoredPrediction> $predictions */
        $predictions = $qb->getQuery()->getResult();

        // Filtrer par date attendue dans les 7 jours, y compris retards jusqu'à un intervalle médian
        $alerts = [];
        foreach ($predictions as $prediction) {
            $expectedDate = $this->calculateExpectedDate($prediction);
            if (null !== $expectedDate && $expectedDate <= $sevenDaysFromNow) {
                // Retard maximum accepté : un intervalle médian
                $medianDays = (int) $prediction->getMedianIntervalDays();
                $earliestDate = $now->modify(sprintf('-%d days', $medianDays));

                if ($expectedDate >= $earliestDate) {
                    $alerts[] = $prediction;
                }
            }
        }

        return $alerts;
    }

    private function calculateExpectedDate(StoredPrediction $prediction): ?\DateTimeImmutable
    {
        $medianDays = $prediction->getMedianIntervalDays();
        $lastOrderDate = $prediction->getLastOrderDate();

        if (null === $medianDays || null === $lastOrderDate) {
            return null;
        }

        $intervalDays = (int) $medianDays;
        $intervalHours = (int) (($medianDays - $intervalDays) * 24);
        $intervalSpec = sprintf('P%dDT%dH', $intervalDays, $intervalHours);

        return $lastOrderDate->add(new \DateInterval($intervalSpec));
    }
}
