<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Form\ClubType;
use App\Repository\ClubRepository;
use App\Repository\ClubMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/clubs')]
class ClubController extends AbstractController
{
    public function __construct(
        private ClubRepository $clubRepository,
        private ClubMemberRepository $clubMemberRepository,
        private EntityManagerInterface $em
    ) {}

    // LIST
    #[Route('', name: 'club_index')]
    public function index(): Response
    {
        return $this->render('clubs/index.html.twig', [
            'clubs' => $this->clubRepository->findAll(),
        ]);
    }

    // SHOW
    #[Route('/{id}', name: 'club_show', methods: ['GET'])]
    public function show(Club $club): Response
    {
        return $this->render('clubs/show.html.twig', [
            'club' => $club,
        ]);
    }

    // SHOW NEW FORM
    #[Route('/new', name: 'club_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $logoFile = $form->get('logoFile')->getData();

            if ($logoFile) {
                $safeName = $slugger->slug(pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeName.'-'.uniqid().'.'.$logoFile->guessExtension();

                $logoFile->move(
                    $this->getParameter('logos_directory'),
                    $newFilename
                );

                $club->setLogo($newFilename);
            }

            $this->em->persist($club);
            $this->em->flush();

            return $this->redirectToRoute('club_index');
        }

        return $this->render('clubs/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}