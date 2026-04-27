<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $roles = $token->getRoleNames();

        $session = $request->getSession();
        if ($session) {
            $targetPath = $this->getTargetPath($session, 'main');
            if ($targetPath) {
                $path = parse_url($targetPath, PHP_URL_PATH) ?? '';

                // Ne pas suivre un chemin protégé si l'utilisateur n'a pas le rôle requis.
                // Sans cette vérification, un client qui visite /admin avant de se connecter
                // se retrouve sur une page 403 après login.
                $requiresAdmin = str_starts_with($path, '/admin');
                $requiresOrga  = str_starts_with($path, '/organisateur')
                               || str_starts_with($path, '/validation');

                $canFollow = true;
                if ($requiresAdmin && !in_array('ROLE_ADMIN', $roles, true)) {
                    $canFollow = false;
                }
                if ($requiresOrga && !in_array('ROLE_ORGANISATEUR', $roles, true)) {
                    $canFollow = false;
                }

                if ($canFollow) {
                    return new RedirectResponse($targetPath);
                }
            }
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin.dashboard'));
        }

        if (in_array('ROLE_ORGANISATEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('organisateur.dashboard'));
        }

        if (in_array('ROLE_CLIENT', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('portefeuille.index'));
        }

        return new RedirectResponse($this->urlGenerator->generate('accueil'));
    }
}
