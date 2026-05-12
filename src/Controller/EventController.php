<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository         $eventRepository,
        private ParticipationRepository $participationRepository,
    ) {}

    // ─── LIST ──────────────────────────────────────────────────────────────────
    #[Route('', name: 'event_index', methods: ['GET'])]
    public function index(): Response
    {
       $events = $this->eventRepository->findBy([], ['dateEvent' => 'ASC']);

        $participatingIn = [];
        if ($this->getUser()) {
            foreach ($events as $event) {
                $participatingIn[$event->getId()] = (bool) $this->participationRepository
                    ->findOneBy(['event' => $event, 'user' => $this->getUser()]);
            }
        }

        return $this->render('events/index.html.twig', [
            'events'          => $events,
            'participatingIn' => $participatingIn,
        ]);
    }

    // ─── DETAIL ────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event): Response
    {
        $participants = $this->participationRepository->findBy(['event' => $event]);

        $isParticipating = false;
        if ($this->getUser()) {
            $isParticipating = (bool) $this->participationRepository
                ->findOneBy(['event' => $event, 'user' => $this->getUser()]);
        }

        $spotsLeft = null;
        if ($event->getMaxParticipants() !== null) {
            $spotsLeft = $event->getMaxParticipants() - count($participants);
        }

        return $this->render('events/show.html.twig', [
            'event'           => $event,
            'participants'    => $participants,
            'isParticipating' => $isParticipating,
            'spotsLeft'       => $spotsLeft,
        ]);
    }
    // ─── VALIDATE (ADMIN) ──────────────────────────────────────────────────────
    #[Route('/{id}/validate', name: 'event_validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validate(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('validate'.$event->getId(), $request->request->get('_token'))) {
            $event->setStatus('Validé');
            $em->flush();
            $this->addFlash('success', 'Event validated successfully.');
        }

        return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
    }
}