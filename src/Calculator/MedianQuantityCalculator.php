<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * Calcule la quantité médiane commandée pour une référence par une entreprise.
 */
final readonly class MedianQuantityCalculator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function calculate(CompanyInterface $company, ProductVariantInterface $variant): int
    {
        // Récupération des quantités commandées (triées par ordre croissant)
        // Règles (comme company-insights CountedOrderSql::counted()) :
        // - checkout_state = 'completed' (commande passée)
        // - state <> 'cancelled' (non annulée)
        $dql = <<<'DQL'
            SELECT oi.quantity
            FROM Sylius\Component\Core\Model\OrderItem oi
            JOIN oi.order o
            JOIN o.customer c
            JOIN Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMember m WITH m.customer = c
            WHERE m.company = :company
            AND oi.variant = :variant
            AND o.checkoutState = 'completed'
            AND o.state != 'cancelled'
            ORDER BY oi.quantity ASC
            DQL;

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('company', $company);
        $query->setParameter('variant', $variant);

        /** @var list<array{quantity: int}> $results */
        $results = $query->getResult();

        if ([] === $results) {
            return 1; // Valeur par défaut si aucune commande
        }

        $quantities = array_map(static fn (array $row): int => $row['quantity'], $results);

        // Calcul de la médiane
        $count = count($quantities);
        $middle = (int) floor($count / 2);

        if (0 === $count % 2) {
            // Nombre pair : moyenne des deux valeurs centrales
            return (int) round(($quantities[$middle - 1] + $quantities[$middle]) / 2);
        }

        // Nombre impair : valeur centrale
        return $quantities[$middle];
    }
}
