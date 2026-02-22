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
        $session = $request->getSession();
        if ($session) {
            $targetPath = $this->getTargetPath($session, 'main');
            if ($targetPath) {
                return new RedirectResponse($targetPath);
            }
        }

        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin.dashboard'));
        }

        if (in_array('ROLE_ORGANISATEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('organisateur.dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('portefeuille.index'));
    }
}
