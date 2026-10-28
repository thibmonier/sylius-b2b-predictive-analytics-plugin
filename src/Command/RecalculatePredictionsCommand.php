<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;

/**
 * Recalcule toutes les prédictions de réassort.
 * Déclenchement quotidien via Messenger (config/messenger.yaml).
 */
#[AsCommand(
    name: 'app:predictive:recalculate',
    description: 'Recalcule toutes les prédictions de réassort pour toutes les entreprises',
)]
final class RecalculatePredictionsCommand extends Command
{
    public function __construct(
        private readonly PredictiveReorderCalculatorInterface $calculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Début du recalcul des prédictions...');

        // TODO: récupérer toutes les entreprises et recalculer pour chacune
        $io->warning('Fonctionnalité de recalcul planifié non encore implémentée (T-217-05)');

        $io->success('Recalcul terminé.');

        return Command::SUCCESS;
    }
}
