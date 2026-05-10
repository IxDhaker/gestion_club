<?php
namespace App\Controller;

use App\Repository\ClubRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ClubRepository $clubRepository, EventRepository $eventRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'clubs' => $clubRepository->findAll(),
            'events' => $eventRepository->findAll(),
        ]);
    }
}