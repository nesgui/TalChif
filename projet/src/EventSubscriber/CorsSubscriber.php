<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS a liste blanche stricte.
 *
 * Regle OWASP : « Access-Control-Allow-Credentials: true » ne peut jamais etre
 * combine a une origine joker ou refletee sans controle. Si l'origine n'est pas
 * explicitement autorisee, aucun en-tete CORS n'est emis (le navigateur bloque).
 */
final class CorsSubscriber implements EventSubscriberInterface
{
    private const METHODES = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
    private const ENTETES = 'Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-TOKEN';
    private const DUREE_CACHE_PREFLIGHT = '3600';

    /** @var string[] */
    private array $originesAutorisees;

    public function __construct(string $corsAllowOrigin = '')
    {
        $this->originesAutorisees = array_values(array_filter(array_map('trim', explode(',', $corsAllowOrigin))));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Court-circuite uniquement les vraies requetes preflight (OPTIONS + Origin
     * + Access-Control-Request-Method) venant d'une origine autorisee.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->getMethod() !== 'OPTIONS' || !$request->headers->has('Access-Control-Request-Method')) {
            return;
        }

        $origine = (string) $request->headers->get('Origin', '');
        if (!$this->estAutorisee($origine)) {
            return;
        }

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->ajouterEntetesCors($origine, $response);
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $origine = (string) $event->getRequest()->headers->get('Origin', '');
        if (!$this->estAutorisee($origine)) {
            return;
        }

        $this->ajouterEntetesCors($origine, $event->getResponse());
    }

    private function ajouterEntetesCors(string $origine, Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origine);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', self::METHODES);
        $response->headers->set('Access-Control-Allow-Headers', self::ENTETES);
        $response->headers->set('Access-Control-Max-Age', self::DUREE_CACHE_PREFLIGHT);
        // Indispensable des que la reponse depend de l'origine (caches partages).
        $response->headers->set('Vary', 'Origin', false);
    }

    private function estAutorisee(string $origine): bool
    {
        return $origine !== '' && \in_array($origine, $this->originesAutorisees, true);
    }
}
