<?php

namespace App\Controller\Backend\Residence;

use App\Entity\Frontend\Appartement;
use App\Form\Frontend\AppartementType;
use App\Repository\Backend\AppartementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Doctrine\Persistence\ManagerRegistry;

final class AppartementController extends AbstractController
{
    #[Route('/admin/appartement',name: 'admin_appartement')]
    public function index(AppartementRepository $appartementRepository): Response
    {
        return $this->render('admin/Residence/indexAppartementBack.html.twig', [
            'appartements' => $appartementRepository->findAll(),
        ]);
    }

    #[Route('/admin/appartement/new', name: 'admin_appartement_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $appartement = new Appartement();
        $form = $this->createForm(AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($appartement);
            $entityManager->flush();

            return $this->redirectToRoute('admin_appartement', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/Residence/newAppartementBack.html.twig', [
            'appartement' => $appartement,
            'form' => $form,
        ]);
    }

    #[Route('/admin/appartement/{id}', name: 'admin_appartement_show')]
    public function show(Appartement $appartement): Response
    {
        return $this->render('admin/Residence/showAppartementBack.html.twig', [
            'appartement' => $appartement,
        ]);
    }

    #[Route('/admin/appartement/edit/{id}', name: 'admin_appartement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_appartement', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/Residence/editAppartementBack.html.twig', [
            'appartement' => $appartement,
            'form' => $form,
        ]);
    }

    #[Route('/admin/appartement/delete/{id}', name: 'admin_appartement_delete')]
    public function delete(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$appartement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($appartement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_appartement', [], Response::HTTP_SEE_OTHER);
    }
}
