<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Form\FeedbackType;
use App\Repository\ClubMemberRepository;
use App\Repository\ClubRepository;
use App\Repository\FeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/feedback')]
final class FeedbackController extends AbstractController
{
    #[Route(name: 'app_feedback_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        FeedbackRepository $feedbackRepository,
        ClubRepository $clubRepository,
        ClubMemberRepository $clubMemberRepository,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Admin and Etudiant see all feedbacks
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ETUDIANT')) {
            $feedbacks = $feedbackRepository->findAll();
        } elseif ($this->isGranted('ROLE_PRESIDENT')) {
            // President sees feedbacks only for events belonging to their clubs
            $clubs = $clubRepository->findBy(['president' => $user]);
            $feedbacks = $clubs ? $feedbackRepository->findByClubs($clubs) : [];
        } elseif ($this->isGranted('ROLE_RESPONSABLE')) {
            // Responsable sees feedbacks for events of clubs they are a member of
            $memberships = $clubMemberRepository->findBy(['user' => $user]);
            $clubs = array_map(fn($m) => $m->getClub(), $memberships);
            $feedbacks = $clubs ? $feedbackRepository->findByClubs($clubs) : [];
        } else {
            $feedbacks = [];
        }

        $canCreate = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ETUDIANT');

        return $this->render('feedback/index.html.twig', [
            'feedbacks' => $feedbacks,
            'canCreate' => $canCreate,
        ]);
    }

    #[Route('/new', name: 'app_feedback_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Only admin and étudiant can create feedbacks
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ETUDIANT')) {
            $this->addFlash('danger', 'Seuls les étudiants peuvent soumettre un feedback.');
            return $this->redirectToRoute('app_feedback_index');
        }

        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            $feedback->setUser($user);
            $feedback->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($feedback);
            $entityManager->flush();

            $this->addFlash('success', 'Votre feedback a été enregistré avec succès.');
            return $this->redirectToRoute('app_feedback_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('feedback/new.html.twig', [
            'feedback' => $feedback,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_feedback_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(
        Feedback $feedback,
        ClubRepository $clubRepository,
        ClubMemberRepository $clubMemberRepository,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->canReadFeedback($feedback, $user, $clubRepository, $clubMemberRepository)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce feedback.');
        }

        return $this->render('feedback/show.html.twig', [
            'feedback' => $feedback,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_feedback_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Feedback $feedback, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_feedback_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('feedback/edit.html.twig', [
            'feedback' => $feedback,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_feedback_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Feedback $feedback, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Admin can delete any; étudiant can only delete their own
        $isOwner = $feedback->getUser() === $user;
        if (!$this->isGranted('ROLE_ADMIN') && !$isOwner) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce feedback.');
        }

        if ($this->isCsrfTokenValid('delete'.$feedback->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($feedback);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_feedback_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Check whether the current user is allowed to read a specific feedback.
     * - Admin & Etudiant: always yes
     * - President: only if the event's club belongs to them
     * - Responsable: only if they are a member of the event's club
     */
    private function canReadFeedback(
        Feedback $feedback,
        \App\Entity\User $user,
        ClubRepository $clubRepository,
        ClubMemberRepository $clubMemberRepository,
    ): bool {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ETUDIANT')) {
            return true;
        }

        $eventClub = $feedback->getEvent()?->getClub();
        if ($eventClub === null) {
            return false;
        }

        if ($this->isGranted('ROLE_PRESIDENT')) {
            $presidentClubs = $clubRepository->findBy(['president' => $user]);
            return in_array($eventClub, $presidentClubs, true);
        }

        if ($this->isGranted('ROLE_RESPONSABLE')) {
            $membership = $clubMemberRepository->findOneBy(['user' => $user, 'club' => $eventClub]);
            return $membership !== null;
        }

        return false;
    }
}
