<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Evenement;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloisonnement vu depuis HTTP.
 *
 * Verifie que le filtre est active sur l'espace organisateur et seulement la :
 * le catalogue public liste toutes les organisations, l'administration garde
 * une vue transverse.
 */
final class CloisonnementHttpTest extends CasFonctionnel
{
    public function testLEspaceOrganisateurNeListeQueLesEvenementsDeSonOrganisation(): void
    {
        $alpha = $this->creerUtilisateur('alpha@talchif.td', 'ORGANISATEUR');
        $beta = $this->creerUtilisateur('beta@talchif.td', 'ORGANISATEUR');

        $this->creerEvenement($alpha, slug: 'concert-alpha', nom: 'Concert Alpha');
        $this->creerEvenement($beta, slug: 'concert-beta', nom: 'Concert Beta');

        $this->client->loginUser($alpha);
        $this->client->request('GET', '/organisateur/evenement');
        self::assertResponseIsSuccessful();

        $contenu = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Concert Alpha', $contenu);
        self::assertStringNotContainsString(
            'Concert Beta',
            $contenu,
            'un organisateur ne doit pas voir les evenements d une autre organisation'
        );
    }

    public function testLeCataloguePublicListeTouteLesOrganisations(): void
    {
        $alpha = $this->creerUtilisateur('alpha@talchif.td', 'ORGANISATEUR');
        $beta = $this->creerUtilisateur('beta@talchif.td', 'ORGANISATEUR');

        $this->creerEvenement($alpha, slug: 'concert-alpha', nom: 'Concert Alpha');
        $this->creerEvenement($beta, slug: 'concert-beta', nom: 'Concert Beta');

        $this->client->request('GET', '/evenements');
        self::assertResponseIsSuccessful();

        $contenu = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Concert Alpha', $contenu);
        self::assertStringContainsString(
            'Concert Beta',
            $contenu,
            'le catalogue public doit rester transverse : le filtre ne s applique pas ici'
        );
    }

    public function testLeCataloguePublicResteTransverseMemeConnecteEnOrganisateur(): void
    {
        $alpha = $this->creerUtilisateur('alpha@talchif.td', 'ORGANISATEUR');
        $beta = $this->creerUtilisateur('beta@talchif.td', 'ORGANISATEUR');

        $this->creerEvenement($alpha, slug: 'concert-alpha', nom: 'Concert Alpha');
        $this->creerEvenement($beta, slug: 'concert-beta', nom: 'Concert Beta');

        $this->client->loginUser($alpha);
        $this->client->request('GET', '/evenements');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            'Concert Beta',
            (string) $this->client->getResponse()->getContent(),
            'un organisateur doit pouvoir acheter chez un confrere'
        );
    }

    /**
     * La vue transverse de l'administration est verifiee au niveau ORM
     * (CloisonnementTenantTest) : la page /admin/evenements ne passe aucune
     * donnee a son template et ne liste donc rien, independamment du tenant.
     */

    public function testUnEvenementDUneAutreOrganisationRenvoieUnRefus(): void
    {
        $alpha = $this->creerUtilisateur('alpha@talchif.td', 'ORGANISATEUR');
        $beta = $this->creerUtilisateur('beta@talchif.td', 'ORGANISATEUR');
        $evenementBeta = $this->creerEvenement($beta, slug: 'concert-beta', nom: 'Concert Beta');

        $this->client->catchExceptions(true);
        $this->client->loginUser($alpha);
        $this->client->request('GET', '/organisateur/evenement/' . $evenementBeta->getId());

        // Le filtre rend l'entite introuvable : 404 plutot que 403, et aucune
        // information sur son existence ne fuit.
        self::assertContains(
            $this->client->getResponse()->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN],
            'aucun acces transverse depuis l espace organisateur'
        );
    }

    public function testUnSecondMembreGereLesEvenementsDeLOrganisation(): void
    {
        $proprietaire = $this->creerUtilisateur('proprio@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($proprietaire, nom: 'Concert Partage');

        $collegue = $this->creerUtilisateur('collegue@talchif.td', 'CLIENT');
        static::getContainer()->get(\App\Service\Tenant\ServiceOrganisation::class)->rattacher(
            $evenement->getOrganisation(),
            $collegue,
            \App\Entity\MembreOrganisation::ROLE_GESTIONNAIRE
        );
        $this->em->flush();
        $this->em->clear();

        $collegue = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'collegue@talchif.td']);

        $this->client->loginUser($collegue);
        $this->client->request('GET', '/organisateur/evenement');
        self::assertResponseIsSuccessful('un gestionnaire accede a l espace organisateur');

        self::assertStringContainsString(
            'Concert Partage',
            (string) $this->client->getResponse()->getContent(),
            'un gestionnaire voit les evenements de son organisation'
        );
    }

    public function testLeFiltreNeFuitPasEntreDeuxRequetes(): void
    {
        $alpha = $this->creerUtilisateur('alpha@talchif.td', 'ORGANISATEUR');
        $beta = $this->creerUtilisateur('beta@talchif.td', 'ORGANISATEUR');
        $this->creerEvenement($alpha, slug: 'concert-alpha', nom: 'Concert Alpha');
        $this->creerEvenement($beta, slug: 'concert-beta', nom: 'Concert Beta');

        // Requete cloisonnee, puis requete publique sur le meme noyau.
        $this->client->loginUser($alpha);
        $this->client->request('GET', '/organisateur/evenement');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/evenements');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            'Concert Beta',
            (string) $this->client->getResponse()->getContent(),
            'le contexte etabli a la requete precedente ne doit pas persister'
        );
    }

    public function testUnEvenementCreeEstRattacheAUneOrganisation(): void
    {
        $organisateur = $this->creerUtilisateur('createur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur, nom: 'Concert Rattache');

        $this->em->clear();
        $relu = $this->em->getRepository(Evenement::class)->findOneBy(['nom' => 'Concert Rattache']);

        self::assertNotNull($relu->getOrganisation(), 'organisation_id est NOT NULL');
        self::assertSame(
            $relu->getOrganisation()->getId(),
            $relu->getOrganisateur()->getAppartenances()->first()->getOrganisation()->getId()
        );
    }
}
