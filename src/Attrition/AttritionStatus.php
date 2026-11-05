<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition;

enum AttritionStatus: string
{
    case Active = 'active';
    case AtRisk = 'at_risk';
    case Critical = 'critical';

    /** Moins de 4 commandes sur 12 mois : pas de score. */
    case InsufficientHistory = 'insufficient_history';

    /** Entreprise dont l'historique couvre moins de 6 mois : aucun statut d'alerte. */
    case TooRecent = 'too_recent';

    /** Les statuts pour lesquels un score existe. */
    public function isScored(): bool
    {
        return \in_array($this, [self::Active, self::AtRisk, self::Critical], true);
    }
}
