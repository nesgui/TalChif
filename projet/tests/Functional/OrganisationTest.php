<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Tenant\ContexteTenant;
use App\Service\Tenant\ServiceOrganisation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onboarding, bascule d'organisation et gestion de l'equipe.
 *
 * Le modele multi-tenant supportait deja plusieurs membres et un taux par
 * organisation ; ces tests couvrent les ecrans qui le rendent utilisable.
 */
final class OrganisationTest extends CasFonctionnel
{
    public function testUnUtilisateurSansOrganisationEstInviteAEnCreerUne(): void
    {
        $utilisateur = $this->creerUtilisateur('nouveau@talchif.td', 'CLIENT');

        $this->client->loginUser($utilisateur);
        $this->client->request('GET', '/organisateur/organisation');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Aucune organisation');
    }

    public function testLaCreationRendLeCreateurProprietaireEtOrganisateur(): void
    {
        $utilisateur = $this->creerUtilisateur('fondateur@talchif.td', 'CLIENT');
        self::assertNotContains('ROLE_ORGANISATEUR', $utilisateur->getRoles());

        $this->client->loginUser($utilisateur);
        $crawler = $this->client->request('GET', '/organisateur/organisation/creer');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Creer l\'organisation', [
            'organisation[nom]' => 'Productions Sahel',
        ]);
        self::assertResponseRedirects('/organisateur/organisation');

        $this->em->clear();
        $organisation = $this->em->getRepository(Organisation::class)->findOneBy(['nom' => 'Productions Sahel']);
        self::assertInstanceOf(Organisation::class, $organisation);
        self::assertSame('productions-sahel', $organisation->getSlug());
        self::assertTrue($organisation->isActif());

        $relu = $this->em->getRepository(User::class)->findOneBy(['email' => 'fondateur@talchif.td']);
        self::assertSame(MembreOrganisation::ROLE_PROPRIETAIRE, $relu->roleDansOrganisation($organisation));
        self::assertContains('ROLE_ORGANISATEUR', $relu->getRoles(), 'la creation accorde le role vendeur');
    }

    public function testLaBasculeChangeLOrganisationDeTravail(): void
    {
        $utilisateur = $this->creerUtilisateur('multi@talchif.td', 'ORGANISATEUR');

        // Deux organisations explicites : le test ne doit dependre d'aucun ordre
        // par defaut, seulement du choix memorise.
        $alpha = $this->creerOrganisation('Alpha', $utilisateur, MembreOrganisation::ROLE_PROPRIETAIRE);
        $beta = $this->creerOrganisation('Beta', $utilisateur, MembreOrganisation::ROLE_PROPRIETAIRE);

        $this->creerEvenementPour($alpha, $utilisateur, 'Concert Alpha', 'concert-alpha');
        $this->creerEvenementPour($beta, $utilisateur, 'Concert Beta', 'concert-beta');

        $this->client->loginUser($utilisateur);

        foreach ([[$alpha, 'Concert Alpha', 'Concert Beta'], [$beta, 'Concert Beta', 'Concert Alpha']] as [$cible, $attendu, $exclu]) {
            $this->client->request('POST', '/organisateur/organisation/basculer', [
                '_token' => $this->jetonDuFormulaire('/organisateur/organisation', '/organisateur/organisation/basculer'),
                'organisation' => $cible->getId(),
            ]);
            self::assertResponseRedirects('/organisateur/organisation');

            $this->client->request('GET', '/organisateur/evenement');
            self::assertResponseIsSuccessful();

            $contenu = (string) $this->client->getResponse()->getContent();
            self::assertStringContainsString($attendu, $contenu, 'la bascule doit persister en session');
            self::assertStringNotContainsString($exclu, $contenu, 'aucune fuite depuis l autre organisation');
        }
    }

    public function testOnNePeutPasBasculerVersUneOrganisationDontOnNEstPasMembre(): void
    {
        $intrus = $this->creerUtilisateur('intrus@talchif.td', 'ORGANISATEUR');
        $autre = $this->creerUtilisateur('autre@talchif.td', 'ORGANISATEUR');

        $service = static::getContainer()->get(ServiceOrganisation::class);
        $service->assurerPour($intrus);
        $this->creerOrganisation('Seconde Intrus', $intrus, MembreOrganisation::ROLE_PROPRIETAIRE);
        $organisationAutre = $service->assurerPour($autre);

        $this->client->loginUser($intrus);
        $this->client->request('POST', '/organisateur/organisation/basculer', [
            '_token' => $this->jetonDuFormulaire('/organisateur/organisation', '/organisateur/organisation/basculer'),
            'organisation' => $organisationAutre->getId(),
        ]);
        self::assertResponseRedirects();

        $contexte = static::getContainer()->get(ContexteTenant::class);
        $relu = $this->em->getRepository(User::class)->findOneBy(['email' => 'intrus@talchif.td']);
        self::assertNotSame(
            $organisationAutre->getId(),
            $contexte->resoudrePour($relu)?->getId(),
            'une organisation etrangere ne doit jamais devenir le contexte'
        );
    }

    public function testUnProprietaireInviteUnMembreExistant(): void
    {
        $proprietaire = $this->creerUtilisateur('proprio@talchif.td', 'ORGANISATEUR');
        $this->creerUtilisateur('collegue@talchif.td', 'CLIENT');
        $organisation = static::getContainer()->get(ServiceOrganisation::class)->assurerPour($proprietaire);

        $this->client->loginUser($proprietaire);
        $jeton = $this->jetonDuFormulaire('/organisateur/organisation/equipe', '/organisateur/organisation/equipe/inviter');

        $this->client->request('POST', '/organisateur/organisation/equipe/inviter', [
            '_token' => $jeton,
            'email' => 'collegue@talchif.td',
            'role' => MembreOrganisation::ROLE_GESTIONNAIRE,
        ]);
        self::assertResponseRedirects('/organisateur/organisation/equipe');

        $this->em->clear();
        $collegue = $this->em->getRepository(User::class)->findOneBy(['email' => 'collegue@talchif.td']);
        $organisationRelue = $this->em->getRepository(Organisation::class)->find($organisation->getId());

        self::assertSame(MembreOrganisation::ROLE_GESTIONNAIRE, $collegue->roleDansOrganisation($organisationRelue));
        self::assertContains('ROLE_ORGANISATEUR', $collegue->getRoles());
    }

    public function testUneInvitationVersUnEmailInconnuNeCreeAucunCompte(): void
    {
        $proprietaire = $this->creerUtilisateur('proprio@talchif.td', 'ORGANISATEUR');
        static::getContainer()->get(ServiceOrganisation::class)->assurerPour($proprietaire);

        $this->client->loginUser($proprietaire);
        $jeton = $this->jetonDuFormulaire('/organisateur/organisation/equipe', '/organisateur/organisation/equipe/inviter');

        $this->client->request('POST', '/organisateur/organisation/equipe/inviter', [
            '_token' => $jeton,
            'email' => 'inconnu@ailleurs.td',
            'role' => MembreOrganisation::ROLE_GESTIONNAIRE,
        ]);
        self::assertResponseRedirects();

        $this->em->clear();
        self::assertNull(
            $this->em->getRepository(User::class)->findOneBy(['email' => 'inconnu@ailleurs.td']),
            'aucun compte ne doit etre cree a l insu de la personne'
        );
    }

    public function testUnGestionnaireNePeutPasGererLEquipe(): void
    {
        $proprietaire = $this->creerUtilisateur('proprio@talchif.td', 'ORGANISATEUR');
        $gestionnaire = $this->creerUtilisateur('gestion@talchif.td', 'CLIENT');

        $service = static::getContainer()->get(ServiceOrganisation::class);
        $organisation = $service->assurerPour($proprietaire);
        $service->rattacher($organisation, $gestionnaire, MembreOrganisation::ROLE_GESTIONNAIRE);
        $this->em->flush();
        $this->em->clear();

        $gestionnaire = $this->em->getRepository(User::class)->findOneBy(['email' => 'gestion@talchif.td']);

        $this->client->catchExceptions(true);
        $this->client->loginUser($gestionnaire);

        // La page reste consultable, en lecture.
        $this->client->request('GET', '/organisateur/organisation/equipe');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Seul un proprietaire');

        $this->client->request('POST', '/organisateur/organisation/equipe/inviter', [
            '_token' => 'peu-importe',
            'email' => 'tiers@talchif.td',
            'role' => MembreOrganisation::ROLE_CONTROLEUR,
        ]);
        self::assertResponseRedirects('/organisateur/organisation/equipe', null, 'jeton invalide : refus avant tout controle de role');
    }

    public function testLeSelecteurNApparaitQuAuDelaDUneOrganisation(): void
    {
        $utilisateur = $this->creerUtilisateur('solo@talchif.td', 'ORGANISATEUR');
        $service = static::getContainer()->get(ServiceOrganisation::class);
        $service->assurerPour($utilisateur);

        $this->client->loginUser($utilisateur);
        $this->client->request('GET', '/organisateur/organisation');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#selecteur-organisation', 'un seul rattachement : rien a choisir');

        $this->creerOrganisation('Seconde', $utilisateur, MembreOrganisation::ROLE_PROPRIETAIRE);

        $this->client->request('GET', '/organisateur/organisation');
        self::assertSelectorExists('#selecteur-organisation', 'deux rattachements : le selecteur apparait');
    }

    private function creerOrganisation(string $nom, User $membre, string $role): Organisation
    {
        $organisation = new Organisation();
        $organisation->setNom($nom);
        $organisation->setSlug(mb_strtolower($nom) . '-' . bin2hex(random_bytes(3)));
        $organisation->setActif(true);
        $this->em->persist($organisation);

        $appartenance = new MembreOrganisation();
        $appartenance->setOrganisation($organisation);
        $appartenance->setUtilisateur($membre);
        $appartenance->setRole($role);
        $this->em->persist($appartenance);
        $this->em->flush();

        return $organisation;
    }

    private function creerEvenementPour(Organisation $organisation, User $organisateur, string $nom, string $slug): void
    {
        $evenement = new \App\Entity\Evenement();
        $evenement->setNom($nom);
        $evenement->setSlug($slug);
        $evenement->setDescription('Jeu de test');
        $evenement->setDateEvenement(new \DateTimeImmutable('+30 days'));
        $evenement->setLieu('Salle');
        $evenement->setAdresse('1 rue du test');
        $evenement->setVille('Ndjamena');
        $evenement->setPlacesDisponibles(50);
        $evenement->setPlacesVendues(0);
        $evenement->setPrixSimple(5000.0);
        $evenement->setIsActive(true);
        $evenement->setIsValide(true);
        $evenement->setOrganisateur($organisateur);
        $evenement->setOrganisation($organisation);
        $this->em->persist($evenement);
        $this->em->flush();
    }
}
