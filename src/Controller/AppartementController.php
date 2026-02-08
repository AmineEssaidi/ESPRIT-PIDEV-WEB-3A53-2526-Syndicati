<?php

namespace App\Controller;

use App\Entity\Residence\Appartement;
use App\Form\Residence\AppartementType;
use App\Repository\Residence\AppartementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/appartement')]
class AppartementController extends AbstractController
{
    #[Route('/', name: 'app_appartement_index', methods: ['GET'])]
    public function index(AppartementRepository $appartementRepository): Response
    {
        return $this->render('appartement/index.html.twig', [
            'appartements' => $appartementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_appartement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FormFactoryInterface $formFactory): Response
    {
        $appartement = new Appartement();
        // Use named form to match template and AdminController
        $form = $formFactory->createNamed('appartement_add', AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('imageA')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('appartements_directory'),
                        $newFilename
                    );
                } catch (\Exception $e) {
                    // Handle exception
                }
                $appartement->setImageA($newFilename);
            }

            // Pack dynamic information into JSON
            $appartement->setAppartementInfo([
                'bloc' => $form->get('bloc')->getData(),
                'floor' => $form->get('floor')->getData(),
                'number' => $form->get('number')->getData(),
                'parking' => $form->get('parking')->getData(),
                'disponible' => $form->get('disponible')->getData(),
            ]);

            $entityManager->persist($appartement);
            $entityManager->flush();

            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => true,
                'message' => 'Apartment registered successfully.',
                'appartement' => [
                    'id' => $appartement->getIdApp(),
                    'type' => $appartement->getTypeA(),
                    'residence' => $appartement->getResidence() ? $appartement->getResidence()->getNomR() : 'N/A',
                    'resId' => $appartement->getResidence() ? $appartement->getResidence()->getIdResidence() : '',
                    'owner' => $appartement->getUser() ? $appartement->getUser()->getEmailUser() : 'N/A',
                    'ownerId' => $appartement->getUser() ? $appartement->getUser()->getIdUser() : '',
                    'parking' => $appartement->isParking() ? 'Yes' : 'No',
                    'isParking' => (bool) $appartement->isParking(),
                    'status' => $appartement->isDisponible() ? 'Available' : 'Occupied',
                    'isAvailable' => (bool) $appartement->isDisponible(),
                    'image' => $appartement->getImageA() ? '/uploads/images/' . $appartement->getImageA() : '/frontend/images/property-placeholder.jpg',
                    'bloc' => $form->get('bloc')->getData(),
                    'floor' => $form->get('floor')->getData(),
                    'number' => $form->get('number')->getData(),
                    'deleteToken' => $this->container->get('security.csrf.token_manager')->getToken('delete' . $appartement->getIdApp())->getValue()
                ]
            ]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
        }

        return $this->render('appartement/new.html.twig', [
            'appartement' => $appartement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{idApp}', name: 'app_appartement_show', methods: ['GET'])]
    public function show(Appartement $appartement): Response
    {
        return $this->render('appartement/show.html.twig', [
            'appartement' => $appartement,
        ]);
    }

    #[Route('/{idApp}/edit', name: 'app_appartement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $idApp, AppartementRepository $repository, EntityManagerInterface $entityManager, SluggerInterface $slugger, \Symfony\Component\Form\FormFactoryInterface $formFactory): Response
    {
        $appartement = $repository->find($idApp);
        if (!$appartement) {
            $this->addFlash('error', 'Critical Error: Apartment with ID ' . $idApp . ' not found in database.');
            return $this->redirectToRoute('admin_residence', ['tab' => 2]);
        }

        $this->addFlash('info', 'DEBUG: Controller started for Edit of Apartment ID: ' . $idApp);

        $form = $formFactory->createNamed('appartement_edit', AppartementType::class, $appartement);

        // --- Pre-fill unmapped fields from JSON for initial render ---
        $info = $appartement->getAppartementInfo();
        if ($info) {
            if (isset($info['bloc']))
                $form->get('bloc')->setData($info['bloc']);
            if (isset($info['floor']))
                $form->get('floor')->setData($info['floor']);
            if (isset($info['number']))
                $form->get('number')->setData($info['number']);
            if (isset($info['parking']))
                $form->get('parking')->setData($info['parking']);
            if (isset($info['disponible']))
                $form->get('disponible')->setData($info['disponible']);
        }

        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            if (!$form->isSubmitted()) {
                $this->addFlash('error', 'Critical Error: Form [appartement_edit] was not detected as submitted in POST request.');
            } else if (!$form->isValid()) {
                $this->addFlash('warning', 'Form submitted but is NOT valid.');
            } else {
                $this->addFlash('info', 'Form submitted and VALID. Proceeding to save...');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('imageA')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('appartements_directory'),
                        $newFilename
                    );
                } catch (\Exception $e) {
                    // Handle exception
                }
                $appartement->setImageA($newFilename);
            }

            // Pack dynamic information into JSON
            $jsonInfo = [
                'bloc' => $form->get('bloc')->getData(),
                'floor' => $form->get('floor')->getData(),
                'number' => $form->get('number')->getData(),
                'parking' => $form->get('parking')->getData(),
                'disponible' => $form->get('disponible')->getData(),
            ];

            // Log for verification
            $this->addFlash('info', 'Updating JSON Info: ' . json_encode($jsonInfo));

            $appartement->setAppartementInfo($jsonInfo);

            // Explicitly sync mapped properties just in case
            $appartement->setParking($form->get('parking')->getData());
            $appartement->setDisponible($form->get('disponible')->getData());

            if ($appartement->getIdApp()) {
                $this->addFlash('info', 'DEBUG: Confirmed existing Entity ID: ' . $appartement->getIdApp() . '. Calling flush().');
            } else {
                $this->addFlash('warning', 'CRITICAL WARNING: Entity ID is LOST/NULL before flush! This will cause an INSERT.');
            }

            $entityManager->flush();

            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => true,
                'message' => 'Apartment updated successfully.',
                'appartement' => [
                    'id' => $appartement->getIdApp(),
                    'type' => $appartement->getTypeA(),
                    'residence' => $appartement->getResidence() ? $appartement->getResidence()->getNomR() : 'N/A',
                    'resId' => $appartement->getResidence() ? $appartement->getResidence()->getIdResidence() : '',
                    'owner' => $appartement->getUser() ? $appartement->getUser()->getEmailUser() : 'N/A',
                    'ownerId' => $appartement->getUser() ? $appartement->getUser()->getIdUser() : '',
                    'parking' => $appartement->isParking() ? 'Yes' : 'No',
                    'isParking' => (bool) $appartement->isParking(),
                    'status' => $appartement->isDisponible() ? 'Available' : 'Occupied',
                    'isAvailable' => (bool) $appartement->isDisponible(),
                    'image' => $appartement->getImageA() ? '/uploads/images/' . $appartement->getImageA() : '/frontend/images/property-placeholder.jpg',
                    'bloc' => $form->get('bloc')->getData(),
                    'floor' => $form->get('floor')->getData(),
                    'number' => $form->get('number')->getData(),
                    'deleteToken' => $this->container->get('security.csrf.token_manager')->getToken('delete' . $appartement->getIdApp())->getValue()
                ]
            ]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
        }

        // Fallback for GET or other cases - redirect back to dashboard
        return $this->redirectToRoute('admin_residence', ['tab' => 2]);
    }

    #[Route('/{idApp}', name: 'app_appartement_delete', methods: ['POST'])]
    public function delete(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $appartement->getIdApp(), $request->request->get('_token'))) {
            $entityManager->remove($appartement);
            $entityManager->flush();
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true, 'message' => 'Apartment deleted successfully.']);
        }

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }
}
