<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EvenementControllerTest extends WebTestCase
{
    public function testListeEvenementsPageOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/evenements');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Événements');
    }

    public function testAccueilPageOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testListeEvenementsRecherche(): void
    {
        $client = static::createClient();
        $client->request('GET', '/evenements', ['q' => 'concert']);

        $this->assertResponseIsSuccessful();
    }
}
