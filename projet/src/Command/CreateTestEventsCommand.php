<?php

namespace App\Command;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-test-events',
    description: 'Crée des événements de test'
)]
class CreateTestEventsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Création des événements de test...</info>');

        // Récupérer l'organisateur
        $organisateur = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'org1@osea.td']);
        
        if (!$organisateur) {
            $output->writeln('<error>Organisateur non trouvé. Exécutez d\'abord app:create-test-data</error>');
            return Command::FAILURE;
        }

        $evenements = [
            [
                'nom' => 'Concert Live - N\'Djamena Music Festival',
                'description' => 'Le plus grand concert de l\'année avec les artistes locaux et internationaux. Une soirée inoubliable avec une programmation variée allant du rap traditionnel au moderne.',
                'slug' => 'concert-live-ndjamena-music-festival',
                'dateEvenement' => new \DateTimeImmutable('2026-03-15 20:00'),
                'lieu' => 'Palais des Sports',
                'adresse' => 'Avenue Charles de Gaulle',
                'ville' => 'N\'Djamena',
                'placesDisponibles' => 500,
                'prixSimple' => 5000,
                'prixVip' => 15000,
                'affichePrincipale' => '/images/evenements/concert-live.jpg',
                'imageBillet' => '/images/billets/concert-ticket.jpg',
            ],
            [
                'nom' => 'Match de Foot - Étoile vs Djamena',
                'description' => 'Le derby tchadien tant attendu ! Venez soutenir votre équipe dans une ambiance électrique. Stade complet garanti.',
                'slug' => 'match-foot-etoile-vs-djamena',
                'dateEvenement' => new \DateTimeImmutable('2026-02-28 16:00'),
                'lieu' => 'Stade Omnisports Idriss Mahamat Ouya',
                'adresse' => 'Route de Farcha',
                'ville' => 'N\'Djamena',
                'placesDisponibles' => 30000,
                'prixSimple' => 2000,
                'prixVip' => 8000,
                'affichePrincipale' => '/images/evenements/match-foot.jpg',
                'imageBillet' => '/images/billets/match-ticket.jpg',
            ],
            [
                'nom' => 'Soirée Urbaine - VIP Night',
                'description' => 'Une soirée exclusive avec DJ international, cocktail premium et ambiance chic. Réservation obligatoire.',
                'slug' => 'soiree-urbaine-vip-night',
                'dateEvenement' => new \DateTimeImmutable('2026-03-01 22:00'),
                'lieu' => 'Golden Lounge',
                'adresse' => 'Avenue Mobutu',
                'ville' => 'N\'Djamena',
                'placesDisponibles' => 150,
                'prixSimple' => 10000,
                'prixVip' => 25000,
                'affichePrincipale' => '/images/evenements/soiree-urbaine.jpg',
                'imageBillet' => '/images/billets/soiree-ticket.jpg',
            ],
        ];

        foreach ($evenements as $eventData) {
            $evenement = new Evenement();
            $evenement->setNom($eventData['nom']);
            $evenement->setDescription($eventData['description']);
            $evenement->setSlug($eventData['slug']);
            $evenement->setDateEvenement($eventData['dateEvenement']);
            $evenement->setLieu($eventData['lieu']);
            $evenement->setAdresse($eventData['adresse']);
            $evenement->setVille($eventData['ville']);
            $evenement->setPlacesDisponibles($eventData['placesDisponibles']);
            $evenement->setPrixSimple($eventData['prixSimple']);
            $evenement->setPrixVip($eventData['prixVip']);
            $evenement->setAffichePrincipale($eventData['affichePrincipale']);
            $evenement->setImageBillet($eventData['imageBillet']);
            $evenement->setOrganisateur($organisateur);
            $evenement->setIsActive(true);
            $evenement->setIsValide(true);
            $evenement->setPlacesVendues(rand(10, 50));

            $this->entityManager->persist($evenement);
        }

        $this->entityManager->flush();

        $output->writeln('<success>Événements de test créés avec succès !</success>');
        $output->writeln('<info>3 événements ont été créés</info>');

        return Command::SUCCESS;
    }
}
