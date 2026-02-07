<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminFinanceController extends AbstractController
{
    #[Route('/admin/finance', name: 'admin.finance.index')]
    public function index(): Response
    {
        return $this->render('admin_finance/index.html.twig', [
            'controller_name' => 'AdminFinanceController',
        ]);
    }
}
