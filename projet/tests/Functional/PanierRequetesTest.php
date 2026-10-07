<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Verrouille le cout en requetes des pages panier et recapitulatif.
 *
 * Ces deux pages faisaient un find() par ligne de panier, chacune avec sa
 * propre copie du code. Le chargement est desormais groupe : une seule lecture
 * de la table evenement, quel que soit le nombre de lignes.
 *
 * La mesure ne compte que les SELECT sur « evenement » : le collecteur Doctrine
 * accumule aussi les requetes des fixtures, qui partagent la connexion.
 */
final class PanierRequetesTest extends CasFonctionnel
{
    public function testLePanierChargeSesEvenementsEnUneSeuleRequete(): void
    {
        $this->remplirPanier(5);

        $this->client->enableProfiler();
        $this->client->request('GET', '/panier');
        self::assertResponseIsSuccessful();

        self::assertSame(
            1,
            $this->lecturesEvenement(),
            '5 lignes de panier doivent couter une seule lecture de la table evenement'
        );
    }

    public function testLeRecapitulatifDAchatChargeSesEvenementsEnUneSeuleRequete(): void
    {
        $this->remplirPanier(8);

        $this->client->enableProfiler();
        $this->client->request('GET', '/achat');
        self::assertResponseIsSuccessful();

        self::assertSame(1, $this->lecturesEvenement(), '8 lignes, une seule lecture');
    }

    public function testUnEvenementDesactiveDisparaitDuPanier(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $actif = $this->creerEvenement($organisateur, slug: 'evenement-actif', nom: 'Concert Disponible');
        $inactif = $this->creerEvenement(
            $organisateur,
            slug: 'evenement-inactif',
            actif: false,
            nom: 'Concert Retire'
        );

        $this->deposerEnSession([
            $actif->getId() => ['quantite' => 1, 'type' => 'SIMPLE'],
            $inactif->getId() => ['quantite' => 1, 'type' => 'SIMPLE'],
        ]);

        $this->client->request('GET', '/panier');
        self::assertResponseIsSuccessful();

        $contenu = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Concert Disponible', $contenu);
        self::assertStringNotContainsString(
            'Concert Retire',
            $contenu,
            'un evenement desactive depuis l ajout ne doit plus apparaitre'
        );
    }

    public function testUnPanierCorrompuNeCassePasLaPage(): void
    {
        // Entrees heritees ou invalides : entier nu, quantite nulle, id absurde.
        $this->deposerEnSession([
            0 => ['quantite' => 1, 'type' => 'SIMPLE'],
            999999 => ['quantite' => 2, 'type' => 'VIP'],
            12 => ['quantite' => 0, 'type' => 'SIMPLE'],
        ]);

        $this->client->request('GET', '/panier');
        self::assertResponseIsSuccessful();
    }

    private function remplirPanier(int $nombre): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');

        $panier = [];
        for ($i = 0; $i < $nombre; $i++) {
            $evenement = $this->creerEvenement($organisateur, slug: 'evenement-' . $i);
            $panier[$evenement->getId()] = ['quantite' => 2, 'type' => 'SIMPLE'];
        }

        $this->deposerEnSession($panier);
    }

    /**
     * @param array<int, array{quantite: int, type: string}> $panier
     */
    private function deposerEnSession(array $panier): void
    {
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('panier', $panier);
        $session->save();

        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId())
        );
    }

    /**
     * Nombre de SELECT emis sur la table evenement.
     */
    private function lecturesEvenement(): int
    {
        $profil = $this->client->getProfile();
        self::assertNotFalse($profil, 'profileur indisponible');

        $lectures = 0;
        foreach ($profil->getCollector('db')->getQueries() as $requetes) {
            foreach ($requetes as $requete) {
                $sql = preg_replace('/\s+/', ' ', (string) $requete['sql']);
                if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'FROM evenement')) {
                    $lectures++;
                }
            }
        }

        return $lectures;
    }
}
