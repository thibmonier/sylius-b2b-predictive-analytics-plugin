<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject;

/**
 * Raisons de l'échec d'une prédiction de réassort.
 */
enum PredictionFailureReason
{
    /** Historique de commandes insuffisant (moins de 3 commandes) */
    case INSUFFICIENT_HISTORY;
}
