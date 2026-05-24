<?php

namespace App\Controller;

use App\Repository\CandidatureRepository;
use App\Repository\ClubMemberRepository;
use App\Repository\ClubRepository;
use App\Repository\EventRepository;
use App\Repository\FeedbackRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ReclamationRepository;
use App\Repository\RecruitmentRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ParticipationRepository $participationRepository,
        ClubRepository $clubRepository,
        UserRepository $userRepository,
        EventRepository $eventRepository,
        ClubMemberRepository $clubMemberRepository,
        RecruitmentRepository $recruitmentRepository,
        CandidatureRepository $candidatureRepository,
        ReclamationRepository $reclamationRepository,
        FeedbackRepository $feedbackRepository,
    ): Response {
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        $participations = $participationRepository->findBy(
            ['user' => $user],
            ['dateParticipation' => 'DESC']
        );

        $upcomingParticipations = 0;
        foreach ($participations as $participation) {
            $eventDate = $participation->getEvent()?->getDateEvent();
            if ($eventDate !== null && $eventDate >= $today) {
                ++$upcomingParticipations;
            }
        }

        $pendingClubs = [];
        $adminStats = null;
        if ($this->isGranted('ROLE_ADMIN')) {
            $pendingClubs = $clubRepository->findBy(['status' => 'En attente']);

            $upcomingEvents = (int) $eventRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->andWhere('e.dateEvent >= :today')
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleScalarResult();

            $averageRating = $feedbackRepository->createQueryBuilder('f')
                ->select('AVG(f.rating)')
                ->getQuery()
                ->getSingleScalarResult();

            $totalClubs = $clubRepository->count([]);
            $activeClubs = $clubRepository->count(['status' => 'Actif']);
            $users = $userRepository->findAll();
            $roleCounts = [
                'admins' => 0,
                'presidents' => 0,
                'responsables' => 0,
                'etudiants' => 0,
            ];

            foreach ($users as $registeredUser) {
                $roles = $registeredUser->getRoles();
                if (in_array('ROLE_ADMIN', $roles, true)) {
                    ++$roleCounts['admins'];
                }
                if (in_array('ROLE_PRESIDENT', $roles, true)) {
                    ++$roleCounts['presidents'];
                }
                if (in_array('ROLE_RESPONSABLE', $roles, true)) {
                    ++$roleCounts['responsables'];
                }
                if (in_array('ROLE_ETUDIANT', $roles, true)) {
                    ++$roleCounts['etudiants'];
                }
            }

            $adminStats = [
                'users' => [
                    'total' => count($users),
                    'admins' => $roleCounts['admins'],
                    'presidents' => $roleCounts['presidents'],
                    'responsables' => $roleCounts['responsables'],
                    'etudiants' => $roleCounts['etudiants'],
                ],
                'clubs' => [
                    'total' => $totalClubs,
                    'active' => $activeClubs,
                    'pending' => count($pendingClubs),
                    'refused' => $clubRepository->count(['status' => 'Refusé']),
                    'activationRate' => $totalClubs > 0 ? round(($activeClubs / $totalClubs) * 100) : 0,
                ],
                'events' => [
                    'total' => $eventRepository->count([]),
                    'validated' => $eventRepository->count(['status' => 'Validé']),
                    'pending' => $eventRepository->count(['status' => 'En attente']),
                    'refused' => $eventRepository->count(['status' => 'Refusé']),
                    'upcoming' => $upcomingEvents,
                ],
                'activity' => [
                    'members' => $clubMemberRepository->count([]),
                    'participations' => $participationRepository->count([]),
                    'recruitments' => $recruitmentRepository->count([]),
                    'candidatures' => $candidatureRepository->count([]),
                ],
                'support' => [
                    'reclamations' => $reclamationRepository->count([]),
                    'openReclamations' => $reclamationRepository->count(['status' => 'En attente']),
                    'feedback' => $feedbackRepository->count([]),
                    'averageRating' => $averageRating !== null ? round((float) $averageRating, 1) : 0,
                ],
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'participations'         => $participations,
            'upcomingParticipations' => $upcomingParticipations,
            'user'                   => $user,
            'pendingClubs'           => $pendingClubs,
            'adminStats'             => $adminStats,
        ]);
    }
}
