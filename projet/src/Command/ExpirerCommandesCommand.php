<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Command\ExpirerCommandesCommand as ExpirerCommandesCommandDTO;
use App\Application\Handler\ExpirerCommandesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:commande:expirer',
    description: 'Expire les commandes en attente de paiement dépassant la date limite',
)]
final class ExpirerCommandesCommand extends Command
{
    public function __construct(
        private ExpirerCommandesHandler $expirerCommandesHandler
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $command = new ExpirerCommandesCommandDTO();
        $count = $this->expirerCommandesHandler->handle($command);
        
        $io->success("{$count} commande(s) expirée(s).");
        return Command::SUCCESS;
    }
}
