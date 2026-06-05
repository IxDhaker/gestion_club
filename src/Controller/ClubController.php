<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Participation;
use App\Entity\Recruitment;
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
    ) {
    }

    // LIST (public)
    #[Route('', name: 'club_index')]
    public function index(): Response
    {
        $allClubs = $this->clubRepository->findAll();
        $clubs = [];

        foreach ($allClubs as $club) {
            if ($this->isGranted('ROLE_ADMIN')) {
                // Admin voit tous les clubs
                $clubs[] = $club;
            } elseif ($club->getStatus() === 'Actif') {
                // Tout le monde voit les clubs actifs
                $clubs[] = $club;
            } elseif (
                $this->getUser()
                && $club->getPresident() === $this->getUser()
                && in_array($club->getStatus(), ['En attente', 'Inactif'], true)
            ) {
                // Le président voit son propre club en attente ou suspendu (pas refusé)
                $clubs[] = $club;
            }
        }

        return $this->render('clubs/index.html.twig', [
            'clubs' => $clubs,
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

    // ─── SUSPENDRE UN CLUB (Admin ou Président) ────────────────────────────────
    #[Route('/{id}/suspend', name: 'club_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function suspend(Club $club): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        $isManager = false;
        if ($user) {
            $clubRepo = $this->em->getRepository(Club::class);
            $isManager = $clubRepo->isManager($club, $user);
        }

        if (!$isAdmin && !$isManager) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à suspendre ce club.');
        }

        if ($club->getStatus() !== 'Actif') {
            $this->addFlash('warning', 'Seul un club actif peut être suspendu.');
            return $this->redirectToRoute(
                $isAdmin ? 'admin_club_index' : 'club_show',
                $isAdmin ? [] : ['id' => $club->getId()],
                Response::HTTP_SEE_OTHER
            );
        }

        $club->setStatus('Inactif');

        if ($club->getPresident()) {
            $who = $isAdmin ? 'l\'administrateur' : 'le président';
            $notif = new Notification();
            $notif->setMessage('Votre club « ' . $club->getNom() . ' » a été suspendu par ' . $who . '.');
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTimeImmutable());
            $notif->setUser($club->getPresident());
            $this->em->persist($notif);
        }

        $this->em->flush();

        $this->addFlash('warning', 'Le club « ' . $club->getNom() . ' » a été suspendu.');

        if ($isAdmin) {
            return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute('club_index', [], Response::HTTP_SEE_OTHER);
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
                $newFilename = $safeName . '-' . uniqid() . '.' . $logoFile->guessExtension();

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
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        $clubRepo = $this->em->getRepository(Club::class);
        $isManager = $user && $clubRepo->isManager($club, $user);

        // Club refusé : admin uniquement
        if ($club->getStatus() === 'Refusé' && !$isAdmin) {
            throw $this->createAccessDeniedException('Ce club a été refusé.');
        }

        // Club en attente ou suspendu : admin ou manager uniquement
        if (in_array($club->getStatus(), ['En attente', 'Inactif'], true) && !$isAdmin && !$isManager) {
            throw $this->createAccessDeniedException('Ce club n\'est pas accessible.');
        }


        $isMember = false;
        if ($this->getUser()) {
            $existing = $this->clubMemberRepository->findOneBy([
                'user' => $this->getUser(),
                'club' => $club,
            ]);
            $isMember = $existing !== null;
        }

        $availableResponsables = [];
        $currentResponsables = [];
        if ($user && $club->getPresident() === $user) { // Seul le président (pas les autres responsables) peut ajouter des responsables
            $allResponsables = $this->userRepository->findByRole('ROLE_RESPONSABLE');
            $currentMembers = $this->clubMemberRepository->findBy(['club' => $club]);
            
            foreach ($currentMembers as $member) {
                if ($member->getRole() === 'Responsable') {
                    $currentResponsables[] = $member->getUser();
                }
            }

            $memberUserIds = array_map(fn($m) => $m->getUser()->getId(), $currentMembers);
            if ($club->getPresident()) {
                $memberUserIds[] = $club->getPresident()->getId();
            }

            foreach ($allResponsables as $resp) {
                if (!in_array($resp->getId(), $memberUserIds, true)) {
                    $availableResponsables[] = $resp;
                }
            }
        }

        return $this->render('clubs/show.html.twig', [
            'club' => $club,
            'isMember' => $isMember,
            'availableResponsables' => $availableResponsables,
            'currentResponsables' => $currentResponsables,
            'isManager' => $isManager,
        ]);
    }

    // ─── AJOUTER UN RESPONSABLE (Président) ──────────────────────────────────
    #[Route('/{id}/add-responsable', name: 'club_add_responsable', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function addResponsable(Request $request, Club $club): Response
    {
        if ($this->getUser() !== $club->getPresident() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Seul le président peut ajouter un responsable.');
        }

        $userId = $request->request->get('user_id');
        if ($userId && $this->isCsrfTokenValid('add_resp' . $club->getId(), (string)$request->request->get('_token'))) {
            $userToAdd = $this->userRepository->find($userId);
            if ($userToAdd && in_array('ROLE_RESPONSABLE', $userToAdd->getRoles(), true)) {
                $existing = $this->clubMemberRepository->findOneBy(['club' => $club, 'user' => $userToAdd]);
                if (!$existing) {
                    $member = new ClubMember();
                    $member->setClub($club);
                    $member->setUser($userToAdd);
                    $member->setRole('Responsable');
                    $member->setJoinedAt(new \DateTimeImmutable());
                    
                    $this->em->persist($member);
                    $this->em->flush();
                    
                    $this->addFlash('success', 'Le responsable a été ajouté au club avec succès.');
                } else {
                    $this->addFlash('warning', 'Cet utilisateur est déjà membre du club.');
                }
            } else {
                $this->addFlash('danger', 'Utilisateur invalide ou n\'a pas le rôle responsable.');
            }
        }

        return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
    }

    // ─── POSTULER A UN CLUB ──────────────────────────────────────────
    #[Route('/{id}/postuler', name: 'club_postuler', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function postuler(Club $club): Response
    {
        // Chercher s'il y a un recrutement pour ce club
        $recruitment = $this->em->getRepository(\App\Entity\Recruitment::class)->findOneBy(['club' => $club], ['id' => 'DESC']);

        if ($recruitment) {
            return $this->redirectToRoute('app_recruitment_show', ['id' => $recruitment->getId()]);
        }

        $this->addFlash('info', 'Il n\'y a pas de recrutement ouvert pour ce club pour le moment.');
        return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
    }

    // ─── DEMANDE D'ACTIVATION (Tâche 5) ───────────────────────────────────────
    #[Route('/{id}/request-activation', name: 'club_request_activation', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function requestActivation(Club $club): Response
    {
        /** @var \App\Entity\User $manager */
        $manager = $this->getUser();
        $clubRepo = $this->em->getRepository(Club::class);

        // Les managers peuvent demander l'activation
        if (!$clubRepo->isManager($club, $manager)) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à gérer ce club.');
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
                'Le responsable ' . $manager->getNom() . ' demande l\'activation du club « ' . $club->getNom() . ' » (ID: ' . $club->getId() . ').'
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
    public function edit(Request $request, Club $club, \Symfony\Component\String\Slugger\SluggerInterface $slugger): Response
    {
        $mode = 'responsable';
        $clubRepo = $this->em->getRepository(Club::class);
        $user = $this->getUser();
        
        if ($user && $clubRepo->isManager($club, $user)) {
            $mode = 'president';
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            $mode = 'all';
        }

        $form = $this->createForm(ClubType::class, $club, [
            'role_mode' => $mode
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();

            if ($logoFile) {
                $safeName = $slugger->slug(pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeName . '-' . uniqid() . '.' . $logoFile->guessExtension();

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
            'clubForm' => $form->createView(),
        ]);
    }

    // DELETE CLUB
    #[Route('/{id}', name: 'club_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function delete(Request $request, Club $club): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        // Seul l'admin ou le président du club peut supprimer
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isPresident = $user && $club->getPresident() === $user;

        if (!$isAdmin && !$isPresident) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce club.');
        }

        if ($this->isCsrfTokenValid('delete' . $club->getId(), (string) $request->request->get('_token'))) {
            // 1. Supprimer les participations liées aux événements du club
            $events = $this->em->getRepository(Event::class)->findBy(['club' => $club]);
            foreach ($events as $event) {
                $participations = $this->em->getRepository(Participation::class)->findBy(['event' => $event]);
                foreach ($participations as $participation) {
                    $this->em->remove($participation);
                }
                $this->em->remove($event);
            }

            // 2. Supprimer les recrutements (les candidatures sont en CASCADE côté DB)
            $recruitments = $this->em->getRepository(Recruitment::class)->findBy(['club' => $club]);
            foreach ($recruitments as $recruitment) {
                $this->em->remove($recruitment);
            }

            // 3. Supprimer les membres du club
            $members = $this->em->getRepository(ClubMember::class)->findBy(['club' => $club]);
            foreach ($members as $member) {
                $this->em->remove($member);
            }

            // 4. Supprimer le club lui-même
            $this->em->remove($club);
            $this->em->flush();
        }

        // Admin redirigé vers la liste admin, président vers la liste publique
        if ($isAdmin) {
            return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('club_index', [], Response::HTTP_SEE_OTHER);
    }
}
