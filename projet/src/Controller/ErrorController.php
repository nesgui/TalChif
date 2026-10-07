<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rendu des pages d'erreur HTTP.
 *
 * Branche sur framework.error_controller : Symfony passe une FlattenException
 * (et non un Throwable) au controleur d'erreur.
 */
final class ErrorController extends AbstractController
{
    /**
     * Codes HTTP disposant d'un template dedie.
     *
     * @var array<int, string>
     */
    private const TEMPLATES_PAR_CODE = [
        403 => 'error/403.html.twig',
        404 => 'error/404.html.twig',
        500 => 'error/500.html.twig',
        503 => 'error/503.html.twig',
    ];

    private const TEMPLATE_PAR_DEFAUT = 'error/error.html.twig';

    public function show(FlattenException $exception): Response
    {
        $code = $exception->getStatusCode();
        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        $template = self::TEMPLATES_PAR_CODE[$code] ?? self::TEMPLATE_PAR_DEFAUT;

        // Aucune donnee d'exception n'est exposee au template (OWASP A05 : fuite d'information).
        return $this->render($template, ['code' => $code], new Response('', $code));
    }
}
