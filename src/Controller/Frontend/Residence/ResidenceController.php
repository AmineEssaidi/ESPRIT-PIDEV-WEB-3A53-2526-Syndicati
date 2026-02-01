<?php

namespace App\Controller\Frontend\Residence;

use App\Entity\Frontend\Residence;

use App\Form\Frontend\ResidenceType;
use App\Repository\Frontend\ResidenceRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Doctrine\Persistence\ManagerRegistry;

//#[Route('/frontend/residence')]
final class ResidenceController extends AbstractController
{
    #[Route('/residence', name:'frontend_residence')]
    public function index(ResidenceRepository $residenceRepository): Response
    {
        return $this->render('frontend/residence/index.html.twig', [
            'residences' => $residenceRepository->findAll(),
        ]);
    }

    #[Route('/newResidence', name: 'frontend_residence_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $residence = new Residence();
        $form = $this->createForm(ResidenceType::class, $residence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($residence);
            $entityManager->flush();

            return $this->redirectToRoute('frontend_residence', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('frontend/residence/new.html.twig', [
            'residence' => $residence,
            'form' => $form,
        ]);
    }

    #[Route('/residence/{id}', name: 'frontend_residence_show')]
    public function show(Residence $residence): Response
    {
        return $this->render('frontend/residence/showResidence.html.twig', [
            'residence' => $residence,
        ]);
    }

    #[Route('/residence/{id}/edit', name: 'frontend_residence_edit')]
    public function edit(Request $request, Residence $residence, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResidenceType::class, $residence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('frontend_residence', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('frontend/residence/edit.html.twig', [
            'residence' => $residence,
            'form' => $form,
        ]);
    }

    #[Route('/residence/{id}', name: 'app_frontend_residence_delete')]
    public function delete(Request $request, Residence $residence, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$residence->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($residence);
            $entityManager->flush();
        }

        return $this->redirectToRoute('frontend_residence', [], Response::HTTP_SEE_OTHER);
    }
}
