<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    public function __construct(string $corsAllowOrigin = '')
    {
        $this->allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $corsAllowOrigin))));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->getMethod() !== 'OPTIONS') {
            return;
        }

        $response = new Response('', 204);
        $this->addCorsHeaders($request->headers->get('Origin', ''), $response);
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $origin = $event->getRequest()->headers->get('Origin', '');
        $this->addCorsHeaders($origin, $event->getResponse());
    }

    private function addCorsHeaders(string $origin, Response $response): void
    {
        $resolved = $this->resolveOrigin($origin);
        $response->headers->set('Access-Control-Allow-Origin', $resolved);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-TOKEN');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }

    private function resolveOrigin(string $origin): string
    {
        if (empty($this->allowedOrigins)) {
            return $origin ?: '*';
        }

        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return $this->allowedOrigins[0];
    }
}
