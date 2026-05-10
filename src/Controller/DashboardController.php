<?php

namespace App\Controller;

use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(ParticipationRepository $participationRepository): Response
    {
        $user = $this->getUser();

        // Fetch the user's participations
        $participations = $participationRepository->findBy(
            ['user' => $user],
            ['registeredAt' => 'DESC']
        );

        return $this->render('dashboard/index.html.twig', [
            'participations' => $participations,
            'user'           => $user,
        ]);
    }
}
