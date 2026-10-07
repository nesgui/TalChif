<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Garde-fous sur le chargement des feuilles de style.
 *
 * Depuis l'extraction du CSS des templates, chaque page declare ses styles par
 * le bloc « stylesheets ». Ces tests empechent trois regressions : du CSS qui
 * repart en inline, un lien emis en double, et un lien casse.
 */
final class FeuillesDeStyleTest extends CasFonctionnel
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesPubliques(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'evenements' => ['/evenements'];
        yield 'panier' => ['/panier'];
        yield 'connexion' => ['/connexion'];
        yield 'a propos' => ['/a-propos'];
    }

    #[DataProvider('pagesPubliques')]
    public function testAucunePagePubliqueNEmetDeCssInline(string $url): void
    {
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        self::assertStringNotContainsString(
            '<style',
            (string) $this->client->getResponse()->getContent(),
            "la page {$url} ne doit plus porter de CSS inline"
        );
    }

    #[DataProvider('pagesPubliques')]
    public function testChaqueFeuilleDeStyleEstDeclareeUneSeuleFois(string $url): void
    {
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $contenu = (string) $this->client->getResponse()->getContent();
        preg_match_all('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $contenu, $trouves);

        $liens = $trouves[1];
        self::assertNotEmpty($liens, "aucune feuille de style sur {$url}");
        self::assertSame(
            array_values(array_unique($liens)),
            $liens,
            "feuille de style declaree plusieurs fois sur {$url}"
        );
    }

    public function testUnePageDuTableauDeBordNeDupliquePasSesFeuillesDeStyle(): void
    {
        $organisateur = $this->creerUtilisateur('organisateur@talchif.td', 'ORGANISATEUR');
        $evenement = $this->creerEvenement($organisateur);

        $this->client->loginUser($organisateur);
        $this->client->request('GET', '/organisateur/evenement/' . $evenement->getId() . '/stats');
        self::assertResponseIsSuccessful();

        $contenu = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<style', $contenu);

        preg_match_all('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $contenu, $trouves);
        $liens = $trouves[1];

        self::assertContains(
            true,
            array_map(static fn (string $l): bool => str_contains($l, 'evenement_stats'), $liens),
            'la feuille de la page doit etre chargee'
        );
        self::assertSame(
            array_values(array_unique($liens)),
            $liens,
            'le layout dashboard ne doit pas emettre deux fois le bloc stylesheets'
        );
    }
}
