<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Repository\ClubRepository;
use App\Repository\ClubMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clubs')]
class ClubController extends AbstractController
{
    public function __construct(
        private ClubRepository       $clubRepository,
        private ClubMemberRepository $clubMemberRepository,
        private EntityManagerInterface $em,
    ) {}

    // ─── LIST ──────────────────────────────────────────────────────────────────
    #[Route('', name: 'club_index', methods: ['GET'])]
    public function index(): Response
    {
        $clubs = $this->clubRepository->findAll();

        // Build a quick map: clubId → bool (is current user already a member?)
        $memberOf = [];
        if ($this->getUser()) {
            foreach ($clubs as $club) {
                $memberOf[$club->getId()] = (bool) $this->clubMemberRepository
                    ->findOneBy(['club' => $club, 'user' => $this->getUser()]);
            }
        }

        return $this->render('clubs/index.html.twig', [
            'clubs'    => $clubs,
            'memberOf' => $memberOf,
        ]);
    }

    // ─── DETAIL ────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'club_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Club $club): Response
    {
        $members = $this->clubMemberRepository->findBy(['club' => $club]);

        $isMember = false;
        if ($this->getUser()) {
            $isMember = (bool) $this->clubMemberRepository
                ->findOneBy(['club' => $club, 'user' => $this->getUser()]);
        }

        return $this->render('clubs/show.html.twig', [
            'club'     => $club,
            'members'  => $members,
            'isMember' => $isMember,
        ]);
    }

    // ─── JOIN ──────────────────────────────────────────────────────────────────
    #[Route('/{id}/join', name: 'club_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function join(Club $club, Request $request): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('join_club_' . $club->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        $user = $this->getUser();

        // Prevent duplicate membership
        $existing = $this->clubMemberRepository->findOneBy(['club' => $club, 'user' => $user]);
        if ($existing) {
            $this->addFlash('warning', 'You are already a member of this club.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        $member = new ClubMember();
        $member->setClub($club);
        $member->setUser($user);
        $member->setJoinedAt(new \DateTimeImmutable());

        $this->em->persist($member);
        $this->em->flush();

        $this->addFlash('success', 'You have successfully joined "' . $club->getName() . '"!');
        return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
    }

    // ─── LEAVE ─────────────────────────────────────────────────────────────────
    #[Route('/{id}/leave', name: 'club_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function leave(Club $club, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave_club_' . $club->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        $member = $this->clubMemberRepository->findOneBy([
            'club' => $club,
            'user' => $this->getUser(),
        ]);

        if (!$member) {
            $this->addFlash('warning', 'You are not a member of this club.');
            return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
        }

        $this->em->remove($member);
        $this->em->flush();

        $this->addFlash('success', 'You have left "' . $club->getName() . '".');
        return $this->redirectToRoute('club_index');
    }
}