<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\ClubRepository;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private ParticipationRepository $participationRepository,
    ) {}

    #[Route('', name: 'event_index', methods: ['GET'])]
    public function index(ClubRepository $clubRepository): Response
    {
        $allEvents = $this->eventRepository->findBy([], ['dateEvent' => 'ASC']);
        $events = [];
        $user = $this->getUser();

        foreach ($allEvents as $event) {
            if ($this->isGranted('ROLE_ADMIN')) {
                $events[] = $event;
            } elseif ($event->getStatus() === 'Validé') {
                $events[] = $event;
            } elseif ($user && $event->getClub() && $clubRepository->isManager($event->getClub(), $user)) {
                $events[] = $event;
            }
        }

        $participatingIn = [];
        if ($this->getUser()) {
            foreach ($events as $event) {
                $participatingIn[$event->getId()] = (bool) $this->participationRepository
                    ->findOneBy(['event' => $event, 'user' => $this->getUser()]);
            }
        }

        return $this->render('events/index.html.twig', [
            'events' => $events,
            'participatingIn' => $participatingIn,
        ]);
    }

    #[Route('/new', name: 'event_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PRESIDENT')]
    public function new(Request $request, ClubRepository $clubRepository, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $managedClubs = $clubRepository->findManagedClubs($user);

        if ($managedClubs === []) {
            $this->addFlash('warning', 'Vous devez gérer un club pour creer un evenement.');

            return $this->redirectToRoute('club_index');
        }

        $event = new Event();
        $event->setStatus('En attente');

        if (count($managedClubs) === 1) {
            $event->setClub($managedClubs[0]);
        }

        $form = $this->createForm(EventType::class, $event, [
            'club_choices' => $managedClubs,
            'submit_label' => 'Creer l\'evenement',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!in_array($event->getClub(), $managedClubs, true)) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas creer un evenement pour ce club.');
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $photoFile */
            $photoFile = $form->has('photoFile') ? $form->get('photoFile')->getData() : null;

            if ($photoFile) {
                $safeFilename = $slugger->slug(pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                $photoFile->move(
                    $this->getParameter('events_directory'),
                    $newFilename
                );

                $event->setPhoto($newFilename);
            }

            $event->setStatus('En attente');
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'Evenement cree avec succes. Il est en attente de validation.');

            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        return $this->render('events/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event, ClubRepository $clubRepository): Response
    {
        $user = $this->getUser();
        if ($event->getStatus() !== 'Validé') {
            if (!$this->isGranted('ROLE_ADMIN') && (!$user || ($event->getClub() && !$clubRepository->isManager($event->getClub(), $user)))) {
                throw $this->createAccessDeniedException('Cet événement n\'est pas encore validé.');
            }
        }
        $participants = $this->participationRepository->findBy([
            'event' => $event,
            'status' => 'Inscrit',
        ]);

        $userParticipation = null;
        if ($this->getUser()) {
            $userParticipation = $this->participationRepository
                ->findOneBy(['event' => $event, 'user' => $this->getUser()]);
        }

        $spotsLeft = null;
        if (method_exists($event, 'getMaxParticipants') && $event->getMaxParticipants() !== null) {
            $reservedPlaces = (int) $this->participationRepository->countByEventAndStatuses($event, [
                'En attente',
                'Inscrit',
            ]);
            /** @phpstan-ignore-next-line */
            $spotsLeft = (int) $event->getMaxParticipants() - $reservedPlaces;
        }

        return $this->render('events/show.html.twig', [
            'event' => $event,
            'participants' => $participants,
            'isParticipating' => $userParticipation !== null && $userParticipation->getStatus() !== 'Refuse',
            'userParticipation' => $userParticipation,
            'spotsLeft' => $spotsLeft,
        ]);
    }

    #[Route('/{id}/edit', name: 'event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PRESIDENT')]
    public function edit(Event $event, Request $request, ClubRepository $clubRepository, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $managedClubs = $clubRepository->findManagedClubs($user);

        if (!in_array($event->getClub(), $managedClubs, true)) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que les evenements de vos clubs.');
        }

        $form = $this->createForm(EventType::class, $event, [
            'club_choices' => $managedClubs,
            'submit_label' => 'Modifier l\'evenement',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!in_array($event->getClub(), $managedClubs, true)) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas transferer cet evenement vers ce club.');
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $photoFile */
            $photoFile = $form->has('photoFile') ? $form->get('photoFile')->getData() : null;

            if ($photoFile) {
                $safeFilename = $slugger->slug(pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                $photoFile->move(
                    $this->getParameter('events_directory'),
                    $newFilename
                );

                $event->setPhoto($newFilename);
            }

            $event->setStatus('En attente');
            $em->flush();

            $this->addFlash('success', 'Evenement modifie avec succes. Il repasse en attente de validation.');

            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        return $this->render('events/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/validate', name: 'event_validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validate(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('validate'.$event->getId(), (string) $request->request->get('_token'))) {
            $event->setStatus('Validé');
            $em->flush();
            $this->addFlash('success', 'Evenement valide avec succes.');
        }

        return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/reject', name: 'event_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('reject'.$event->getId(), (string) $request->request->get('_token'))) {
            $event->setStatus('Refusé');
            $em->flush();
            $this->addFlash('danger', 'Evenement refuse.');
        }

        return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
    }
}
