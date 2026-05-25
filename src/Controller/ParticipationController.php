<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Participation;
use App\Entity\User;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class ParticipationController extends AbstractController
{
    private const STATUS_PENDING = 'En attente';
    private const STATUS_ACCEPTED = 'Inscrit';
    private const STATUS_REFUSED = 'Refuse';

    public function __construct(
        private ParticipationRepository $participationRepository,
        private EntityManagerInterface  $em,
    ) {}

    #[Route('/president/participations', name: 'president_participations', methods: ['GET'])]
    #[IsGranted('ROLE_PRESIDENT')]
    public function receivedParticipations(): Response
    {
        /** @var User $president */
        $president = $this->getUser();
        $participations = $this->participationRepository->findReceivedByPresident($president);

        $stats = [
            self::STATUS_PENDING => 0,
            self::STATUS_ACCEPTED => 0,
            self::STATUS_REFUSED => 0,
        ];

        foreach ($participations as $participation) {
            $status = $participation->getStatus();
            if (isset($stats[$status])) {
                ++$stats[$status];
            }
        }

        return $this->render('participation/president_received.html.twig', [
            'participations' => $participations,
            'stats' => $stats,
        ]);
    }

    // ─── REGISTER ──────────────────────────────────────────────────────────────
    #[Route('/{id}/participate', name: 'event_participate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function participate(Event $event, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('participate_' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        $user = $this->getUser();

        $existing = $this->participationRepository->findOneBy(['event' => $event, 'user' => $user]);
        if ($existing) {
            if ($existing->getStatus() === self::STATUS_REFUSED) {
                $existing->setStatus(self::STATUS_PENDING);
                $existing->setDateParticipation(new \DateTime());
                $this->notifyPresident($event, $user);
                $this->em->flush();

                $this->addFlash('success', 'Votre demande de participation a ete renvoyee au president.');
                return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
            }

            $this->addFlash('warning', 'Vous avez deja une participation pour cet evenement.');
            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        // Check available spots
        if (method_exists($event, 'getMaxParticipants') && $event->getMaxParticipants() !== null) {
            $current = $this->participationRepository->countByEventAndStatuses($event, [
                self::STATUS_PENDING,
                self::STATUS_ACCEPTED,
            ]);
            if ($current >= $event->getMaxParticipants()) {
                $this->addFlash('danger', 'Sorry, this event is fully booked.');
                return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
            }
        }

        $participation = new Participation();
        $participation->setEvent($event);
        $participation->setUser($user);
        $participation->setDateParticipation(new \DateTime());
        $participation->setStatus($this->requiresPresidentApproval($event, $user) ? self::STATUS_PENDING : self::STATUS_ACCEPTED);

        $this->em->persist($participation);
        $this->notifyPresident($event, $user);
        $this->em->flush();

        $message = $participation->getStatus() === self::STATUS_PENDING
            ? 'Votre demande de participation a ete envoyee au president.'
            : 'Vous etes maintenant inscrit a "' . $event->getTitre() . '".';

        $this->addFlash('success', $message);
        return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
    }

    #[Route('/participations/{id}/accept', name: 'participation_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PRESIDENT')]
    public function accept(Participation $participation, Request $request): Response
    {
        $this->denyAccessUnlessPresidentOwnsParticipation($participation);

        if (!$this->isCsrfTokenValid('accept_participation_' . $participation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('president_participations');
        }

        $participation->setStatus(self::STATUS_ACCEPTED);
        $this->notifyStudent($participation, 'Votre participation a "' . $participation->getEvent()?->getTitre() . '" a ete acceptee.');
        $this->em->flush();

        $this->addFlash('success', 'Participation acceptee.');
        return $this->redirectToRoute('president_participations', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/participations/{id}/refuse', name: 'participation_refuse', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PRESIDENT')]
    public function refuse(Participation $participation, Request $request): Response
    {
        $this->denyAccessUnlessPresidentOwnsParticipation($participation);

        if (!$this->isCsrfTokenValid('refuse_participation_' . $participation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('president_participations');
        }

        $participation->setStatus(self::STATUS_REFUSED);
        $this->notifyStudent($participation, 'Votre participation a "' . $participation->getEvent()?->getTitre() . '" a ete refusee.');
        $this->em->flush();

        $this->addFlash('warning', 'Participation refusee.');
        return $this->redirectToRoute('president_participations', [], Response::HTTP_SEE_OTHER);
    }

    // ─── CANCEL ────────────────────────────────────────────────────────────────
    #[Route('/{id}/cancel', name: 'event_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Event $event, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cancel_' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        $participation = $this->participationRepository->findOneBy([
            'event' => $event,
            'user'  => $this->getUser(),
        ]);

        if (!$participation) {
            $this->addFlash('warning', 'You were not registered for this event.');
            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        $this->em->remove($participation);
        $this->em->flush();

        $this->addFlash('success', 'Your registration has been cancelled.');
        return $this->redirectToRoute('event_index');
    }

    // ─── PARTICIPANTS LIST ──────────────────────────────────────────────────────
    #[Route('/{id}/participants', name: 'event_participants', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function participants(Event $event): Response
    {
        $participants = $this->participationRepository->findBy(['event' => $event]);

        return $this->render('events/participants.html.twig', [
            'event'        => $event,
            'participants' => $participants,
        ]);
    }

    private function requiresPresidentApproval(Event $event, mixed $user): bool
    {
        $president = $event->getClub()?->getPresident();

        return $president instanceof User
            && $user instanceof User
            && $president->getId() !== $user->getId();
    }

    private function notifyPresident(Event $event, mixed $student): void
    {
        $president = $event->getClub()?->getPresident();
        if (!$president instanceof User || !$student instanceof User || $president->getId() === $student->getId()) {
            return;
        }

        $notification = new Notification();
        $notification->setMessage($student->getNom() . ' demande a participer a "' . $event->getTitre() . '".');
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTimeImmutable());
        $notification->setUser($president);

        $this->em->persist($notification);
    }

    private function notifyStudent(Participation $participation, string $message): void
    {
        $student = $participation->getUser();
        if (!$student instanceof User) {
            return;
        }

        $notification = new Notification();
        $notification->setMessage($message);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTimeImmutable());
        $notification->setUser($student);

        $this->em->persist($notification);
    }

    private function denyAccessUnlessPresidentOwnsParticipation(Participation $participation): void
    {
        $president = $participation->getEvent()?->getClub()?->getPresident();
        $currentUser = $this->getUser();

        if (!$president instanceof User || !$currentUser instanceof User || $president->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez gerer que les participations de vos evenements.');
        }
    }
}
