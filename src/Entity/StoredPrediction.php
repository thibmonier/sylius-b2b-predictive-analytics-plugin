<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * Prédiction de réassort persistée pour une référence d'une entreprise.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tmonier_predictive_stored_prediction')]
#[ORM\UniqueConstraint(name: 'UNIQ_company_variant', columns: ['company_id', 'variant_id'])]
class StoredPrediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(name: 'calculated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $calculatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CompanyInterface::class)]
        #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private CompanyInterface $company,
        #[ORM\ManyToOne(targetEntity: ProductVariantInterface::class)]
        #[ORM\JoinColumn(name: 'variant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private ProductVariantInterface $variant,
        #[ORM\Column(name: 'median_interval_days', type: 'float', nullable: true)]
        private ?float $medianIntervalDays,
        #[ORM\Column(name: 'last_order_date', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $lastOrderDate,
        #[ORM\Column(name: 'order_count', type: 'integer')]
        private int $orderCount,
        #[ORM\Column(name: 'reason', type: 'string', length: 50, nullable: true)]
        private ?string $reason,
        #[ORM\Column(name: 'explanation', type: 'text')]
        private string $explanation,
    ) {
        $this->calculatedAt = new \DateTimeImmutable();
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

    public function getMedianIntervalDays(): ?float
    {
        return $this->medianIntervalDays;
    }

    public function getLastOrderDate(): ?\DateTimeImmutable
    {
        return $this->lastOrderDate;
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function getCalculatedAt(): \DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function update(
        ?float $medianIntervalDays,
        ?\DateTimeImmutable $lastOrderDate,
        int $orderCount,
        ?string $reason,
        string $explanation,
    ): void {
        $this->medianIntervalDays = $medianIntervalDays;
        $this->lastOrderDate = $lastOrderDate;
        $this->orderCount = $orderCount;
        $this->reason = $reason;
        $this->explanation = $explanation;
        $this->calculatedAt = new \DateTimeImmutable();
    }
}
