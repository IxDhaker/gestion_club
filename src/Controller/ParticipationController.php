<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Participation;
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
    public function __construct(
        private ParticipationRepository $participationRepository,
        private EntityManagerInterface  $em,
    ) {}

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

        // Prevent duplicate participation
        if ($this->participationRepository->findOneBy(['event' => $event, 'user' => $user])) {
            $this->addFlash('warning', 'You are already registered for this event.');
            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        // Check available spots
        if ($event->getMaxParticipants() !== null) {
            $current = $this->participationRepository->count(['event' => $event]);
            if ($current >= $event->getMaxParticipants()) {
                $this->addFlash('danger', 'Sorry, this event is fully booked.');
                return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
            }
        }

        $participation = new Participation();
        $participation->setEvent($event);
        $participation->setUser($user);
        $participation->setRegisteredAt(new \DateTimeImmutable());

        $this->em->persist($participation);
        $this->em->flush();

        $this->addFlash('success', 'You are now registered for "' . $event->getName() . '"!');
        return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
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
    #[IsGranted('ROLE_USER')]
    public function participants(Event $event): Response
    {
        $participants = $this->participationRepository->findBy(['event' => $event]);

        return $this->render('events/participants.html.twig', [
            'event'        => $event,
            'participants' => $participants,
        ]);
    }
}