<?php

namespace App\Command;

use App\Service\Payment\PaymentVerificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-payments',
    description: 'Vérifie les paiements en attente et met à jour les balances'
)]
class CheckPaymentsCommand extends Command
{
    public function __construct(
        private PaymentVerificationService $paymentVerificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Vérification des paiements en attente');

        $results = $this->paymentVerificationService->checkPendingPayments();

        if (empty($results)) {
            $io->success('Aucun paiement en attente à vérifier.');
            return Command::SUCCESS;
        }

        $io->section('Résultats de la vérification');

        $tableRows = [];
        foreach ($results as $result) {
            $status = $result['success'] ? '✅ Succès' : '⏳ En attente';
            $tableRows[] = [
                $result['reference'],
                $result['depositId'],
                $status
            ];
        }

        $io->table(
            ['Référence', 'Deposit ID', 'Statut'],
            $tableRows
        );

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);

        $io->success("Vérification terminée : {$successCount}/{$totalCount} paiements traités avec succès.");

        return Command::SUCCESS;
    }
}
