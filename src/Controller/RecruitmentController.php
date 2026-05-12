<?php

namespace App\Controller;

use App\Entity\Recruitment;
use App\Form\RecruitmentType;
use App\Repository\RecruitmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recruitment')]
final class RecruitmentController extends AbstractController
{
    #[Route(name: 'app_recruitment_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(RecruitmentRepository $recruitmentRepository): Response
    {
        return $this->render('recruitment/index.html.twig', [
            'recruitments' => $recruitmentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_recruitment_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $recruitment = new Recruitment();
        $form = $this->createForm(RecruitmentType::class, $recruitment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($recruitment);
            $entityManager->flush();

            return $this->redirectToRoute('app_recruitment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recruitment/new.html.twig', [
            'recruitment' => $recruitment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recruitment_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Recruitment $recruitment): Response
    {
        return $this->render('recruitment/show.html.twig', [
            'recruitment' => $recruitment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recruitment_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function edit(Request $request, Recruitment $recruitment, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RecruitmentType::class, $recruitment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_recruitment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recruitment/edit.html.twig', [
            'recruitment' => $recruitment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recruitment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function delete(Request $request, Recruitment $recruitment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recruitment->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($recruitment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recruitment_index', [], Response::HTTP_SEE_OTHER);
    }
}
