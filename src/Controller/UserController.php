<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
final class UserController extends AbstractController
{
    private const ALLOWED_ROLES = [
        'ROLE_ETUDIANT',
        'ROLE_RESPONSABLE',
        'ROLE_PRESIDENT',
        'ROLE_ADMIN',
    ];

    #[Route('/admin', name: 'app_user_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users'         => $userRepository->findAll(),
            'allowed_roles' => self::ALLOWED_ROLES,
        ]);
    }

    #[Route('/profile', name: 'app_user_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $originalEmail = $user->getEmail();
        $originalNom = $user->getNom();

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emailOwner = $userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($emailOwner && $emailOwner !== $user) {
                $form->get('email')->addError(new FormError('Cet email est deja utilise.'));
            }

            $nameOwner = $userRepository->findOneBy(['nom' => $user->getNom()]);
            if ($nameOwner && $nameOwner !== $user) {
                $form->get('nom')->addError(new FormError('Ce nom est deja utilise.'));
            }

            $newPassword = $form->get('plainPassword')->getData();
            $currentPassword = $form->get('currentPassword')->getData();

            if ($newPassword && !$passwordHasher->isPasswordValid($user, (string) $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError('Mot de passe actuel incorrect.'));
            }

            if ($form->isValid()) {
                if ($newPassword) {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                }

                $entityManager->flush();

                $this->addFlash('success', 'Votre profil a ete mis a jour.');
                return $this->redirectToRoute('app_user_profile', [], Response::HTTP_SEE_OTHER);
            }

            $user->setEmail((string) $originalEmail);
            $user->setNom((string) $originalNom);
        }

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'profileForm' => $form,
        ]);
    }

    /** Change the primary role of a user (admin only). */
    #[Route('/{id}/change-role', name: 'app_user_change_role', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changeRole(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('change_role'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        /** @var \App\Entity\User $admin */
        $admin = $this->getUser();
        if ($admin === $user) {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier votre propre rôle.');
            return $this->redirectToRoute('app_user_index');
        }

        $newRole = $request->getPayload()->getString('role');
        if (!in_array($newRole, self::ALLOWED_ROLES, true)) {
            $this->addFlash('danger', 'Rôle invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setRoles([$newRole]);
        $em->flush();

        $this->addFlash('success', 'Le rôle de « ' . $user->getNom() . ' » a été changé en ' . $newRole . '.');
        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $admin */
        $admin = $this->getUser();
        if ($admin === $user) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}

