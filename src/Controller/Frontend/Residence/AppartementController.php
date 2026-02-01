<?php

namespace App\Controller\Frontend\Residence;

use App\Entity\Frontend\Appartement;
use App\Form\Frontend\AppartementType;
use App\Repository\Frontend\AppartementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/appartement')]
final class AppartementController extends AbstractController
{
    #[Route(name: 'frontend_residence_appartement', methods: ['GET'])]
    public function index(AppartementRepository $appartementRepository): Response
    {
        return $this->render('frontend/residence/indexAppartement.html.twig', [
            'appartements' => $appartementRepository->findAll(),
        ]);
    }
 

    #[Route('/appartement/new', name: 'frontend_residence_appartement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $appartement = new Appartement();
        $form = $this->createForm(AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($appartement);
            $entityManager->flush();

            return $this->redirectToRoute('frontend_residence_appartement', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('frontend/residence/newAppartement.html.twig', [
            'appartement' => $appartement,
            'form' => $form,
        ]);
    }

    #[Route('/appartement/{id}', name: 'frontend_residence_appartement_show', methods: ['GET'])]
    public function show(Appartement $appartement): Response
    {
        return $this->render('frontend/residence/showAppartement.html.twig', [
            'appartement' => $appartement,
        ]);
    }

    #[Route('/appartement/{id}/edit', name: 'frontend_residence_appartement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('frontend_residence_appartement', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('frontend/residence/editAppartement.html.twig', [
            'appartement' => $appartement,
            'form' => $form,
        ]);
    }

    #[Route('/appartement/delete/{id}', name: 'frontend_residence_appartement_delete', methods: ['POST'])]
    public function delete(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$appartement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($appartement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('frontend_residence_appartement', [], Response::HTTP_SEE_OTHER);
    }
}
