<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * Masquage d'une alerte de réassort par un utilisateur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tmonier_predictive_dismissed_prediction')]
#[ORM\UniqueConstraint(name: 'UNIQ_dismissed_company_variant_customer', columns: ['company_id', 'variant_id', 'customer_id'])]
class DismissedPrediction
{
    public const TYPE_NEVER = 'never';

    public const TYPE_LATER = 'later';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CompanyInterface::class)]
        #[ORM\JoinColumn(name: 'company_id', nullable: false)]
        private readonly CompanyInterface $company,
        #[ORM\ManyToOne(targetEntity: ProductVariantInterface::class)]
        #[ORM\JoinColumn(name: 'variant_id', nullable: false)]
        private readonly ProductVariantInterface $variant,
        #[ORM\ManyToOne(targetEntity: CustomerInterface::class)]
        #[ORM\JoinColumn(name: 'customer_id', nullable: false)]
        private readonly CustomerInterface $customer,
        #[ORM\Column(type: 'string', length: 10)]
        private readonly string $type,
        #[ORM\Column(name: 'dismissed_at', type: 'datetime_immutable')]
        private readonly \DateTimeImmutable $dismissedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): CompanyInterface
    {
        return $this->company;
    }

    public function getVariant(): ProductVariantInterface
    {
        return $this->variant;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDismissedAt(): \DateTimeImmutable
    {
        return $this->dismissedAt;
    }
}
