<?php

namespace App\Controller\Residence;

use App\Entity\Residence\Residence;
use App\Form\Residence\ResidenceType;
use App\Service\SmsGenerator;
use App\Repository\Residence\ResidenceRepository;
use App\Repository\User\UserRepository;
use App\Entity\Residence\Appartement;
use App\Form\Residence\AppartementType;
use App\Repository\Residence\AppartementRepository;
use App\Service\PageStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use App\Entity\User\User;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;


#[Route('/residence')]
class ResidenceController extends AbstractController
{
    #[Route('/', name: 'app_residence_index', methods: ['GET', 'POST'])]
    public function index(Request $request, ResidenceRepository $residenceRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $query = $residenceRepository->createQueryBuilder('r')
            ->orderBy('r.dateAjout', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3 // 3 residences per page
        );

        return $this->render('frontend/residence/index.html.twig', [
    'smsSent' => false,
    'residences' => $pagination,
        ]);
    }

    #[Route('/admin', name: 'admin_residence')]
    public function adminIndex(PageStatusService $pageStatusService, Request $request, ResidenceRepository $residenceRepository, AppartementRepository $appartementRepository, FormFactoryInterface $formFactory): Response
    {
        $pageStatusService->setPageStatus('residence', 'online');

        // --- Residence Logic ---
        $residences = $residenceRepository->findAll();
        $residenceAddForm = $this->createForm(ResidenceType::class, new Residence(), [
            'action' => $this->generateUrl('admin_residence_add'),
            'method' => 'POST',
        ]);

        // --- Appartement Logic ---
        $appartements = $appartementRepository->findAll();
        $appartementAddForm = $formFactory->createNamed('appartement_add', AppartementType::class, new Appartement(), [
            'action' => $this->generateUrl('app_appartement_new'),
            'method' => 'POST',
        ]);

        $residenceEditForm = $formFactory->createNamed('residence_edit', ResidenceType::class, new Residence());
        $appartementEditForm = $formFactory->createNamed('appartement_edit', AppartementType::class, new Appartement());

        return $this->render('admin/Residence/index.html.twig', [
            'residences' => $residences,
            'appartements' => $appartements,
            'residenceAddForm' => $residenceAddForm->createView(),
            'residenceEditForm' => $residenceEditForm->createView(),
            'appartementAddForm' => $appartementAddForm->createView(),
            'appartementEditForm' => $appartementEditForm->createView(),
        ]);
    }

    #[Route('/admin/add', name: 'admin_residence_add', methods: ['POST'])]
    public function addResidence(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $residence = new Residence();
        $form = $this->createForm(ResidenceType::class, $residence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blocsData = $form->get('nBlocs')->getData();
            if (is_array($blocsData)) {
                $residence->setNBlocs(implode(',', $blocsData));
            }

            $imageFile = $form->get('imageR')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('residences_directory'), $newFilename);
                    $residence->setImageR($newFilename);
                } catch (\Exception $e) {
                }
            }

            if (!$residence->getDateAjout()) {
                $residence->setDateAjout(new \DateTime());
            }

            $em->persist($residence);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Residence added successfully.',
                'residence' => [
                    'id' => $residence->getIdResidence(),
                    'name' => $residence->getNomR(),
                    'address' => $residence->getAdresse(),
                    'date' => $residence->getDateAjout()->format('Y-m-d H:i'),
                    'apartments' => $residence->getNAppartements(),
                    'floors' => $residence->getNEtages(),
                    'blocs' => $residence->getNBlocs(),
                    'image' => $residence->getImageR() ? '/uploads/images/' . $residence->getImageR() : '/frontend/images/property-placeholder.jpg',
                    'deleteToken' => $csrfTokenManager->getToken('residence_delete')->getValue()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/admin/{id}/edit', name: 'admin_residence_edit', methods: ['POST'])]
    public function editResidence(int $id, Request $request, ResidenceRepository $residenceRepository, EntityManagerInterface $em, SluggerInterface $slugger, FormFactoryInterface $formFactory): JsonResponse
    {
        $residence = $residenceRepository->find($id);
        if (!$residence) {
            return new JsonResponse(['success' => false, 'message' => 'Residence not found.'], 404);
        }

        $form = $formFactory->createNamed('residence_edit', ResidenceType::class, $residence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blocsData = $form->get('nBlocs')->getData();
            if (is_array($blocsData)) {
                $residence->setNBlocs(implode(',', $blocsData));
            }

            $imageFile = $form->get('imageR')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('residences_directory'), $newFilename);
                    $residence->setImageR($newFilename);
                } catch (\Exception $e) {
                }
            }

            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Residence updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors)], 400);
    }

    #[Route('/admin/{id}/delete', name: 'admin_residence_delete', methods: ['POST'])]
    public function deleteResidence(int $id, Request $request, ResidenceRepository $residenceRepository, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        try {
            $token = $request->request->get('_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('residence_delete', $token ?? ''))) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
            }

            $residence = $residenceRepository->find($id);
            if (!$residence) {
                return new JsonResponse(['success' => false, 'message' => 'Residence not found.'], 404);
            }

            $em->remove($residence);
            $em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Residence deleted successfully.']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // --- Appartement Methods ---

    #[Route('/appartement/new', name: 'app_appartement_new', methods: ['POST'])]
    public function newAppartement(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FormFactoryInterface $formFactory, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $appartement = new Appartement();
        $form = $formFactory->createNamed('appartement_add', AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageA')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('appartements_directory'), $newFilename);
                    $appartement->setImageA($newFilename);
                } catch (\Exception $e) {
                }
            }

            $appartement->setAppartementInfo([
                'bloc' => $form->get('bloc')->getData(),
                'floor' => $form->get('floor')->getData(),
                'number' => $form->get('number')->getData(),
                'parking' => $form->get('parking')->getData(),
                'disponible' => $form->get('disponible')->getData(),
            ]);

            $entityManager->persist($appartement);
            $entityManager->flush();

            return new JsonResponse([
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
                    'deleteToken' => $csrfTokenManager->getToken('appartement_delete')->getValue()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/appartement/{idApp}/edit', name: 'app_appartement_edit', methods: ['POST'])]
    public function editAppartement(Request $request, int $idApp, AppartementRepository $repository, EntityManagerInterface $entityManager, SluggerInterface $slugger, FormFactoryInterface $formFactory, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $appartement = $repository->find($idApp);
        if (!$appartement) {
            return new JsonResponse(['success' => false, 'message' => 'Apartment not found.'], 404);
        }

        $form = $formFactory->createNamed('appartement_edit', AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageA')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('appartements_directory'), $newFilename);
                    $appartement->setImageA($newFilename);
                } catch (\Exception $e) {
                }
            }

            $jsonInfo = [
                'bloc' => $form->get('bloc') ? $form->get('bloc')->getData() : null,
                'floor' => $form->get('floor') ? $form->get('floor')->getData() : null,
                'number' => $form->get('number') ? $form->get('number')->getData() : null,
                'parking' => $form->get('parking')->getData(),
                'disponible' => $form->get('disponible')->getData(),
            ];
            $appartement->setAppartementInfo($jsonInfo);
            $appartement->setParking($form->get('parking')->getData());
            $appartement->setDisponible($form->get('disponible')->getData());

            $entityManager->flush();

            return new JsonResponse([
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
                    'bloc' => $jsonInfo['bloc'],
                    'floor' => $jsonInfo['floor'],
                    'number' => $jsonInfo['number'],
                    'deleteToken' => $csrfTokenManager->getToken('appartement_delete')->getValue()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/appartement/{idApp}/delete', name: 'app_appartement_delete', methods: ['POST'])]
    public function deleteAppartement(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->isCsrfTokenValid('appartement_delete', $request->request->get('_token'))) {
            $entityManager->remove($appartement);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => 'Apartment deleted successfully.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }

    #[Route('/{id}/details', name: 'app_residence_details', methods: ['GET'])]
    public function details(int $id, ResidenceRepository $residenceRepository, Request $request): Response
    {
        $residence = $residenceRepository->find($id);
        if (!$residence) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Residence not found.'], 404);
            }
            throw $this->createNotFoundException('Residence not found.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('frontend/residence/show_modal.html.twig', [
                'residence' => $residence,
            ]);
        }

        return $this->render('frontend/residence/show.html.twig', [
            'residence' => $residence,
]);
    }

        #[Route('/{id}', name: 'residence_apartments_frame')]
        public function AfficherAppartements($id, ResidenceRepository $residenceRepository): Response
        {
            $residence = $residenceRepository->find($id);
            
            return $this->render('frontend/residence/appartements.html.twig', [
                'appartements' => $residence->getAppartements(),
                'residence' => $residence,
            ]);
        }

#[Route('/{id}/sendSms', name: 'send_sms', methods: ['GET', 'POST'])]
public function sendSms(SmsGenerator $smsGenerator, Request $request, UserRepository $userRep, ResidenceRepository $residenceRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
{
    $session = $request->getSession();
    $userId = (int) $session->get('user')['id'];
    $user = $userRep->find($userId);
    $name = $user->getFirstName();
    $text =$user->getEmailUser();
    $number_test = $_ENV['twilio_to_number'];
    
    $smsGenerator->sendSms($number_test, $name, $text);

            $query = $residenceRepository->createQueryBuilder('r')
            ->orderBy('r.dateAjout', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3 // 3 residences per page
        );

    
    return $this->render('frontend/residence/index.html.twig', [
        'smsSent' => true,
        'residences' => $pagination,
    ]);
}


    #[Route('/pdf/{id}', 'pdf_residence')]
    public function GenererPDFResidence($id, Request $request, GotenbergPdfInterface $gotenbergPdf, ResidenceRepository $residenceRepository): Response
    {
        $residence = $residenceRepository->find($id);
        return $gotenbergPdf->html()->content('frontend/residence/pdf_residence.html.twig', [
            'appartements' => $residence->getAppartements(),
           'residence' => $residence,
       ])
        ->generate()
        ->stream()
    ;
    }
}

