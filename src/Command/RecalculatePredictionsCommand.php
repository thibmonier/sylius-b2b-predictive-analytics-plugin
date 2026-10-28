<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Command;

use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;

/**
 * Recalcule toutes les prédictions de réassort pour toutes les entreprises.
 *
 * Planification recommandée : cron quotidien ou Symfony Scheduler.
 */
#[AsCommand(
    name: 'tmonier:predictive:recalculate',
    description: 'Recalcule toutes les prédictions de réassort pour toutes les entreprises',
)]
final class RecalculatePredictionsCommand extends Command
{
    public function __construct(
        private readonly PredictiveReorderCalculatorInterface $calculator,
        private readonly RepositoryInterface $companyRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Début du recalcul des prédictions...');

        // Preuve rouge : tableau vide au lieu de findAll()
        /** @var array<CompanyInterface> $companies */
        $companies = $this->companyRepository->findBy(['id' => -1]);  // Aucune entreprise avec ID -1
        $totalCompanies = 0;
        $totalPredictions = 0;

        foreach ($companies as $company) {
            $count = $this->calculator->recalculateForCompany($company);
            $totalPredictions += $count;
            ++$totalCompanies;

            $io->writeln(sprintf(
                '  - %s : %d prédiction%s recalculée%s',
                $company->getName() ?? $company->getCode(),
                $count,
                $count > 1 ? 's' : '',
                $count > 1 ? 's' : '',
            ));
        }

        $io->success(sprintf(
            'Recalcul terminé : %d prédiction%s pour %d entreprise%s.',
            $totalPredictions,
            $totalPredictions > 1 ? 's' : '',
            $totalCompanies,
            $totalCompanies > 1 ? 's' : '',
        ));

        return Command::SUCCESS;
    }
}
