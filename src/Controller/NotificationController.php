<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $em,
    ) {}

    // ─── LIST ──────────────────────────────────────────────────────────────────
    #[Route('', name: 'app_notification_index', methods: ['GET'])]
    public function index(): Response
    {
        $notifications = $this->notificationRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    // ─── MARK AS READ ──────────────────────────────────────────────────────────
    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markAsRead(Notification $notification, Request $request): Response
    {
        if ($notification->getUser() === $this->getUser()) {
            $notification->setIsRead(true);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_notification_index');
    }

    // ─── MARK ALL AS READ ──────────────────────────────────────────────────────
    #[Route('/read-all', name: 'app_notification_read_all', methods: ['POST'])]
    public function markAllAsRead(Request $request): Response
    {
        $notifications = $this->notificationRepository->findBy([
            'user'   => $this->getUser(),
            'isRead' => false,
        ]);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->em->flush();
        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');

        return $this->redirectToRoute('app_notification_index');
    }

    // ─── DELETE ────────────────────────────────────────────────────────────────
    #[Route('/{id}/delete', name: 'app_notification_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Notification $notification, Request $request): Response
    {
        if ($notification->getUser() === $this->getUser()) {
            $this->em->remove($notification);
            $this->em->flush();
            $this->addFlash('success', 'Notification supprimée.');
        }

        return $this->redirectToRoute('app_notification_index');
    }
}