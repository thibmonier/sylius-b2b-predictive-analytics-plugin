# Plugin prédictif de réassort pour Sylius

Plugin Sylius 2 qui calcule automatiquement les rythmes de réassort par entreprise et référence, pour anticiper les besoins d'achat avant la rupture de stock.

## Fonctionnalités

- **Calcul d'intervalle médian** : analyse l'historique de commandes pour déterminer le rythme d'achat typique de chaque référence par entreprise
- **Prédiction explicable** : conserve l'explication du calcul (« commandée 5 fois, tous les 28 jours en médiane »)
- **Date de réassort attendue** : estime la prochaine date de commande selon le rythme habituel
- **Commande de recalcul** : mise à jour de toutes les prédictions en une commande planifiable

## Prérequis

- PHP 8.2 ou supérieur
- Sylius 2.2.9 ou 2.3+
- Plugin [company-accounts](https://github.com/thibmonier/sylius-company-accounts-plugin) (^0.1) installé
- Plugin [company-insights](https://github.com/thibmonier/sylius-company-insights-plugin) (^0.2) installé

## Installation

```bash
composer require tmonier/sylius-b2b-predictive-analytics-plugin
```

Activez le plugin dans `config/bundles.php` :

```php
return [
    // ...
    Tmonier\SyliusPredictiveAnalyticsPlugin\TmonierSyliusPredictiveAnalyticsPlugin::class => ['all' => true],
];
```

Exécutez les migrations pour créer la table des prédictions :

```bash
php bin/console doctrine:migrations:migrate
```

## Usage

### Calcul d'une prédiction

```php
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;

$prediction = $calculator->calculate($variant, $company);

if ($prediction->isPredicted()) {
    echo $prediction->explanation; // "commandée 5 fois, tous les 28 jours en médiane"
    echo $prediction->expectedReorderDate->format('Y-m-d'); // Date attendue du prochain réassort
} else {
    echo $prediction->explanation; // "historique insuffisant (1 commande, minimum 3 requises)"
}
```

### Recalcul planifié

Le plugin inclut une commande pour recalculer toutes les prédictions :

```bash
php bin/console tmonier:predictive:recalculate
```

Cette commande peut être planifiée quotidiennement via cron :

```cron
0 2 * * * cd /path/to/project && php bin/console tmonier:predictive:recalculate
```

## Règles de calcul

- **Minimum 3 commandes** : une prédiction nécessite au moins 3 commandes complétées
- **Commandes comptées** : seules les commandes complétées et non annulées sont prises en compte
- **Médiane robuste** : la médiane n'est pas faussée par les valeurs extrêmes, contrairement à la moyenne
- **Cloisonnement** : chaque entreprise a ses propres prédictions, sans mélange

## Démo

Une instance de démonstration est disponible sur [sylius-b2b.thibaut-monier.fr](https://sylius-b2b.thibaut-monier.fr).

## Auteur

[Thibaut Monier](https://www.linkedin.com/in/thibautmonier/) – Développement et maintenance

## Licence

MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.
