<?php

namespace App\Controller\Backend\Residence;

use App\Entity\Backend\Appartement;
use App\Form\Backend\AppartementType;
use App\Repository\Backend\AppartementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

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
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger,
    #[Autowire('%kernel.project_dir%/public/images/appartements')] string $dirImage
    ): Response
    {
        $appartement = new Appartement();
        $form = $this->createForm(AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $img = $form->get('image_a')->getData();
            if ($img) {
                $originalFilename = pathinfo($img->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$img->guessExtension();

                $img->move($dirImage, $newFilename);

                $appartement->setImageA($newFilename);

            }
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
    public function edit(Request $request, Appartement $appartement, EntityManagerInterface $entityManager,
    SluggerInterface $slugger,
    #[Autowire('%kernel.project_dir%/public/images/appartements')] string $dirImage): Response
    {
        $form = $this->createForm(AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $img = $form->get('image_a')->getData();
            if ($img) {
                $originalFilename = pathinfo($img->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$img->guessExtension();

                $img->move($dirImage, $newFilename);

                $appartement->setImageA($newFilename);

            }
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
