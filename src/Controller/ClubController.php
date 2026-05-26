<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Notification;
use App\Form\ClubType;
use App\Repository\ClubRepository;
use App\Repository\ClubMemberRepository;
use App\Repository\UserRepository;
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
        private UserRepository $userRepository,
        private EntityManagerInterface $em
    ) {}

    // LIST (public)
    #[Route('', name: 'club_index')]
    public function index(): Response
    {
        return $this->render('clubs/index.html.twig', [
            'clubs' => $this->clubRepository->findAll(),
        ]);
    }

    // ─── ADMIN : gestion des clubs ─────────────────────────────────────────────
    #[Route('/admin/clubs', name: 'admin_club_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(): Response
    {
        $all = $this->clubRepository->findAll();

        // Regrouper par statut pour les statistiques
        $stats = ['Actif' => 0, 'En attente' => 0, 'Inactif' => 0, 'Refusé' => 0, 'Autre' => 0];
        foreach ($all as $club) {
            $s = $club->getStatus();
            if (isset($stats[$s])) {
                $stats[$s]++;
            } else {
                $stats['Autre']++;
            }
        }

        return $this->render('clubs/admin_index.html.twig', [
            'clubs' => $all,
            'stats' => $stats,
        ]);
    }

    // ─── SUSPENDRE UN CLUB (Admin) ─────────────────────────────────────────────
    #[Route('/{id}/suspend', name: 'club_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function suspend(Club $club): Response
    {
        $club->setStatus('Inactif');

        if ($club->getPresident()) {
            $notif = new Notification();
            $notif->setMessage('Votre club « ' . $club->getNom() . ' » a été suspendu par l\'administrateur.');
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTimeImmutable());
            $notif->setUser($club->getPresident());
            $this->em->persist($notif);
        }

        $this->em->flush();

        $this->addFlash('warning', 'Le club « ' . $club->getNom() . ' » a été suspendu.');
        return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
    }

    // SHOW NEW FORM
    #[Route('/new', name: 'club_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $logoFile */
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

            /** @var \App\Entity\User|null $president */
            $president = $this->getUser();
            $club->setPresident($president);

            $this->em->persist($club);
            $this->em->flush();

            return $this->redirectToRoute('club_index');
        }

        return $this->render('clubs/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // SHOW
    #[Route('/{id}', name: 'club_show', methods: ['GET'])]
    public function show(Club $club): Response
    {
        $isMember = false;
        if ($this->getUser()) {
            $existing = $this->clubMemberRepository->findOneBy([
                'user' => $this->getUser(),
                'club' => $club,
            ]);
            $isMember = $existing !== null;
        }

        return $this->render('clubs/show.html.twig', [
            'club'     => $club,
            'isMember' => $isMember,
        ]);
    }

    // ─── REJOINDRE UN CLUB (Tâche 6) ──────────────────────────────────────────
    #[Route('/{id}/join', name: 'club_join', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function join(Club $club): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Vérifier si déjà membre
        $existing = $this->clubMemberRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        if ($existing) {
            $this->addFlash('warning', 'Vous êtes déjà membre de ce club.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        $member = new ClubMember();
        $member->setUser($user);
        $member->setClub($club);
        $member->setRole('membre');
        $member->setJoinedAt(new \DateTimeImmutable());

        $this->em->persist($member);
        $this->em->flush();

        $this->addFlash('success', 'Vous avez rejoint le club « ' . $club->getNom() . ' » avec succès !');

        return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
    }

    // ─── DEMANDE D'ACTIVATION (Tâche 5) ───────────────────────────────────────
    #[Route('/{id}/request-activation', name: 'club_request_activation', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function requestActivation(Club $club): Response
    {
        /** @var \App\Entity\User $president */
        $president = $this->getUser();

        // Seul le président du club peut demander l'activation
        if ($club->getPresident() !== $president) {
            $this->addFlash('danger', 'Vous n\'êtes pas le président de ce club.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        if ($club->getStatus() === 'Actif') {
            $this->addFlash('info', 'Ce club est déjà actif.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        $club->setStatus('En attente');

        // Notifier tous les admins
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        foreach ($admins as $admin) {
            $notif = new Notification();
            $notif->setMessage(
                'Le président ' . $president->getNom() . ' demande l\'activation du club « ' . $club->getNom() . ' » (ID: ' . $club->getId() . ').'
            );
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTimeImmutable());
            $notif->setUser($admin);
            $this->em->persist($notif);
        }

        $this->em->flush();

        $this->addFlash('success', 'Votre demande d\'activation a été envoyée à l\'administrateur.');

        return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
    }

    // ─── ACTIVER UN CLUB (Admin) ───────────────────────────────────────────────
    #[Route('/{id}/activate', name: 'club_activate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function activate(Club $club): Response
    {
        $club->setStatus('Actif');

        // Notifier le président si existant
        if ($club->getPresident()) {
            $notif = new Notification();
            $notif->setMessage('Votre club « ' . $club->getNom() . ' » a été activé par l\'administrateur.');
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTimeImmutable());
            $notif->setUser($club->getPresident());
            $this->em->persist($notif);
        }

        $this->em->flush();

        $this->addFlash('success', 'Le club « ' . $club->getNom() . ' » a été activé.');

        return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
    }

    // ─── REFUSER UN CLUB (Admin) ───────────────────────────────────────────────
    #[Route('/{id}/refuse', name: 'club_refuse', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function refuse(Club $club): Response
    {
        $club->setStatus('Refusé');

        // Notifier le président si existant
        if ($club->getPresident()) {
            $notif = new Notification();
            $notif->setMessage('Votre demande d\'activation du club « ' . $club->getNom() . ' » a été refusée par l\'administrateur.');
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTimeImmutable());
            $notif->setUser($club->getPresident());
            $this->em->persist($notif);
        }

        $this->em->flush();

        $this->addFlash('warning', 'Le club « ' . $club->getNom() . ' » a été refusé.');

        return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
    }

    // EDIT CLUB
    #[Route('/{id}/edit', name: 'club_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function edit(Request $request, Club $club, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $logoFile */
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

            $this->em->flush();

            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        return $this->render('clubs/edit.html.twig', [
            'club' => $club,
            'form' => $form->createView(),
        ]);
    }

    // DELETE CLUB
    #[Route('/{id}', name: 'club_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Club $club): Response
    {
        if ($this->isCsrfTokenValid('delete' . $club->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($club);
            $this->em->flush();
        }

        return $this->redirectToRoute('club_index');
    }
}
