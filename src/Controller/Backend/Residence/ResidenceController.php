<?php

namespace App\Controller\Backend\Residence;

use App\Entity\Backend\Residence;
use App\Form\Backend\ResidenceType;
use App\Repository\Backend\ResidenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

use Doctrine\Persistence\ManagerRegistry;

final class ResidenceController extends AbstractController
{
    #[Route('/admin/residence', name: 'admin_residence')]
    public function index(ResidenceRepository $residenceRepository, Request $req): Response
    {
        $residences=$residenceRepository->findAll();
        
        $search=$req->query->get('cherche_residence');
        $critere=$req->query->get('tri_r');

        if ($search)
            $residences=$residenceRepository->findByName($search);
        if ($critere)
            $residences=$residenceRepository->triResidence($critere);
        
        return $this->render('admin/Residence/index.html.twig', [
            'residences' => $residences,
        ]);
    }

    #[Route('/admin/newResidence', name: 'admin_residence_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger,
    #[Autowire('%kernel.project_dir%/public/images/residence')] string $dirImage
    ): Response
    {
        $residence = new Residence();
        $form = $this->createForm(ResidenceType::class, $residence);
        $residence->setNAppartements(0);
        $residence->setDateAjout(new \DateTime());
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {

            $img = $form->get('image_r')->getData();
            if ($img) {
                $originalFilename = pathinfo($img->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$img->guessExtension();

                $img->move($dirImage, $newFilename);

                $residence->setImageR($newFilename);

            }
            $entityManager->persist($residence);
            $entityManager->flush();

            return $this->redirectToRoute('admin_residence', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/Residence/new.html.twig', [
            'residence' => $residence,
            'form' => $form,
        ]);
    }

    #[Route('/admin/residence/{id}', name: 'admin_residence_show')]
    public function show(Residence $residence): Response
    {
        return $this->render('admin/Residence/show.html.twig', [
            'residence' => $residence,
        ]);
    }

    #[Route('/admin/residence/edit/{id}', name: 'admin_residence_edit')]
    public function edit(Request $request, Residence $residence, EntityManagerInterface $entityManager,
    SluggerInterface $slugger,
    #[Autowire('%kernel.project_dir%/public/images/residence')] string $dirImage): Response
    {
        $form = $this->createForm(ResidenceType::class, $residence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $img = $form->get('image_r')->getData();
            if ($img) {
                $originalFilename = pathinfo($img->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$img->guessExtension();

                $img->move($dirImage, $newFilename);

                $residence->setImageR($newFilename);

            }
            $entityManager->flush();

            return $this->redirectToRoute('admin_residence', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/Residence/edit.html.twig', [
            'residence' => $residence,
            'form' => $form,
        ]);
    }

    #[Route('/admin/residence/delete/{id}', name: 'admin_residence_delete', methods: ['POST'])]
    public function delete(Request $request, Residence $residence, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$residence->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($residence);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_residence');
    }


}
