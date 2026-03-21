<?php

namespace App\Controller;

use App\Repository\LogSecuriteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/securite')]
#[IsGranted('ROLE_ADMIN')]
final class AdminSecuriteController extends AbstractController
{
    public function __construct(
        private LogSecuriteRepository $logSecuriteRepository,
    ) {}

    #[Route('', name: 'admin.securite.index')]
    public function index(Request $request): Response
    {
        $page   = max(1, $request->query->getInt('page', 1));
        $limit  = 100;
        $offset = ($page - 1) * $limit;

        $logs  = $this->logSecuriteRepository->findRecentPaginated($limit, $offset);
        $total = $this->logSecuriteRepository->countTotal();

        return $this->render('admin_securite/index.html.twig', [
            'logs'       => $logs,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ]);
    }
}
