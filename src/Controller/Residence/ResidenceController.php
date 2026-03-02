<?php

namespace App\Controller\Residence;

use App\Service\FormErrorHelperTrait;

use App\Entity\Residence\Residence;
use App\Form\Residence\ResidenceType;
use App\Service\SmsGenerator;
use App\Repository\Residence\ResidenceRepository;
use App\Repository\User\UserRepository;
use App\Entity\Residence\Appartement;
use App\Form\Residence\AppartementType;
use App\Repository\Residence\AppartementRepository;
use App\Repository\Residence\MaintenanceRepository;
use App\Service\PageStatusService;
use App\Service\RecommendationAppartement;
use App\Service\User\NotificationService;
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
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use App\Service\MachineLearning;
use App\Service\MaintenancePrediction;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/residence')]
class ResidenceController extends AbstractController
{
    use FormErrorHelperTrait;
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
    public function adminIndex(
        PageStatusService $pageStatusService,
        Request $request,
        ResidenceRepository $residenceRepository,
        AppartementRepository $appartementRepository,
        MaintenanceRepository $maintenanceRepository,
        FormFactoryInterface $formFactory,
        MachineLearning $predictor,
        ParameterBagInterface $params
    ): Response {
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

        // --- AI Predictions ---
        $modelPath = $params->get('kernel.project_dir') . '/var/models/appartement_prix.model';
        $predictions = [];
        if (file_exists($modelPath)) {
            try {
                $predictor->loadModel($modelPath);
                foreach ($appartements as $appartement) {
                    $prediction = $predictor->predict($appartement);
                    $predictions[$appartement->getIdApp()] = is_numeric($prediction) ? round((float) $prediction) : 0;
                }
            } catch (\Exception $e) {
                // Model loading failed, predictions remain empty
            }
        }

        $predictionsmaintenance = [];
        foreach ($appartements as $appartement) {
            $maintenance = $appartement->getMaintenance();
            $predictionsmaintenance[$appartement->getIdApp()] = $maintenance?->getRecommendationIa();
        }

        // --- Maintenance Logic ---
        $maintenances = $maintenanceRepository->findAll();

        return $this->render('admin/Residence/index.html.twig', [
            'residences' => $residences,
            'appartements' => $appartements,
            'maintenances' => $maintenances,
            'predictions' => $predictions,
            'predictionsmaintenance' => $predictionsmaintenance,
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

        if ($form->isSubmitted()) {
            return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
        }

        return new JsonResponse(['success' => false, 'message' => 'Form submission failed.'], 400);
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

        if ($form->isSubmitted()) {
            return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
        }

        return new JsonResponse(['success' => false, 'message' => 'Form submission failed.'], 400);
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
                    'superficie' => $appartement->getSuperficie() ?: '—',
                    'prixLocation' => $appartement->getPrixLocation() ?: '—',
                    'prixVente' => $appartement->getPrixVente() ?: '—',
                    'dateConstruction' => $appartement->getDateConstruction() ? $appartement->getDateConstruction()->format('Y-m-d') : '—',
                    'bloc' => $form->get('bloc')->getData(),
                    'floor' => $form->get('floor')->getData(),
                    'number' => $form->get('number')->getData(),
                    'deleteToken' => $csrfTokenManager->getToken('appartement_delete')->getValue(),
                    'maintenancePrediction' => $appartement->getMaintenance() ? $appartement->getMaintenance()->getRecommendationIa() : null
                ]
            ]);
        }

        if ($form->isSubmitted()) {
            return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
        }

        return new JsonResponse(['success' => false, 'message' => 'Form submission failed.'], 400);
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
                    'superficie' => $appartement->getSuperficie() ?: '—',
                    'prixLocation' => $appartement->getPrixLocation() ?: '—',
                    'prixVente' => $appartement->getPrixVente() ?: '—',
                    'dateConstruction' => $appartement->getDateConstruction() ? $appartement->getDateConstruction()->format('Y-m-d') : '—',
                    'bloc' => $jsonInfo['bloc'],
                    'floor' => $jsonInfo['floor'],
                    'number' => $jsonInfo['number'],
                    'deleteToken' => $csrfTokenManager->getToken('appartement_delete')->getValue()
                ]
            ]);
        }

        if ($form->isSubmitted()) {
            return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
        }

        return new JsonResponse(['success' => false, 'message' => 'Form submission failed.'], 400);
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
    public function AfficherAppartements($id, ResidenceRepository $residenceRepository, Request $request): Response
    {
        $residence = $residenceRepository->find($id);

        if ($request->isXmlHttpRequest() || $request->query->get('ajax')) {
            return $this->render('frontend/residence/index.html.twig', [
                'appartements' => $residence->getAppartements(),
                'residence' => $residence,
                'targetBlock' => 'apartments'
            ]);
        }

        return $this->render('frontend/residence/index.html.twig', [
            'appartements' => $residence->getAppartements(),
            'residence' => $residence,
        ]);
    }

    #[Route('/{id}/sendSms', name: 'send_sms', methods: ['GET', 'POST'])]
    public function sendSms(int $id, SmsGenerator $smsGenerator, Request $request, UserRepository $userRep, AppartementRepository $appartementRepository, ResidenceRepository $residenceRepository, \Knp\Component\Pager\PaginatorInterface $paginator, NotificationService $notificationService): Response
    {
        $session = $request->getSession();
        $userData = $session->get('user');

        if (!$userData) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Non authentifié'], 401);
            }
            return $this->redirectToRoute('auth_sign_in');
        }

        $user = $userRep->find($userData['id']);
        if (!$user) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Utilisateur non trouvé'], 404);
            }
            return $this->redirectToRoute('auth_sign_in');
        }

        $destNumber = $user->getPhone();
        if (!$destNumber) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Veuillez ajouter un numéro de téléphone à votre profil pour recevoir le SMS.'], 400);
            }
            $this->addFlash('error', 'Veuillez ajouter un numéro de téléphone à votre profil.');
            return $this->redirectToRoute('app_residence_index');
        }

        $appartement = $appartementRepository->find($id);

        $name = $user->getFirstName() . ' ' . $user->getLastName();
        $text = "Intéressé par l'appartement " . ($appartement ? $appartement->getTypeA() . " à " . $appartement->getResidence()->getNomR() : "ID: " . $id);
        $text .= " - Contact: " . $user->getEmailUser();

        try {
            $smsGenerator->sendSms($destNumber, $name, $text);

            // Create a persistence notification
            $notificationService->notify(
                $user,
                'SUCCESS',
                'residence_sms',
                $id,
                'SMS Envoyé',
                "Votre demande pour l'appartement " . ($appartement ? $appartement->getTypeA() : "proposé") . " a été envoyée par SMS."
            );

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success', 'message' => 'SMS envoyé avec succès à votre numéro (' . $destNumber . ') !']);
            }
        } catch (\Exception $e) {
            // Log error in database too
            $notificationService->notify(
                $user,
                'ERROR',
                'residence_sms',
                $id,
                'Échec SMS',
                "L'envoi du SMS a échoué: " . $e->getMessage()
            );

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Échec de l\'envoi du SMS: ' . $e->getMessage()], 500);
            }
            $this->addFlash('error', 'Échec de l\'envoi du SMS: ' . $e->getMessage());
        }

        // Fallback for non-AJAX calls
        $query = $residenceRepository->createQueryBuilder('r')
            ->orderBy('r.dateAjout', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('frontend/residence/index.html.twig', [
            'smsSent' => true,
            'residences' => $pagination,
        ]);
    }

    #[Route('/appartementform/{id}', name: 'app_appartement_show')]
    public function showApp($id, Appartement $appartement, RecommendationAppartement $recommender, Request $request): Response
    {
        $app_recommende = $recommender->AppartementsSimilaires($appartement, limit: 4);

        if ($request->isXmlHttpRequest() || $request->query->get('ajax')) {
            return $this->render('frontend/residence/index.html.twig', [
                'appartement' => $appartement,
                'app_recommende' => $app_recommende,
                'targetBlock' => 'details'
            ]);
        }

        return $this->render('frontend/residence/index.html.twig', [
            'appartement' => $appartement,
            'app_recommende' => $app_recommende,
        ]);
    }

    #[Route('/pdf/{id}', 'pdf_residence')]
    public function GenererPDFResidence($id, Request $request, GotenbergPdfInterface $gotenbergPdf, ResidenceRepository $residenceRepository): Response
    {
        $residence = $residenceRepository->find($id);
        $response = $gotenbergPdf->html()
            ->printBackground(true)
            ->content('frontend/residence/pdf_residence.html.twig', [
                'appartements' => $residence->getAppartements(),
                'residence' => $residence,
            ])
            ->generate()
            ->stream();

        $response->headers->set('Content-Disposition', "attachment; filename=\"residence_{$residence->getIdResidence()}.pdf\"");

        return $response;
    }

    private ?MachineLearning $cachedPredictor = null;

    #[Route('/admin/prediction', name: 'prediction_prix', methods: ['POST'])]
    public function predictPrice(
        Request $request,
        MachineLearning $predictor,
        ParameterBagInterface $params
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['superficie']) || !isset($data['type'])) {
                return $this->json([
                    'success' => false,
                    'predicted_price' => 0,
                    'formatted_price' => 'Données invalides'
                ], 400);
            }

            $superficie = (float) $data['superficie'];
            if ($superficie <= 0) {
                return $this->json([
                    'success' => false,
                    'predicted_price' => 0,
                    'formatted_price' => 'Surface invalide'
                ], 400);
            }

            $modelPath = $params->get('kernel.project_dir') . '/var/models/appartement_prix.model';

            if (!file_exists($modelPath)) {
                return $this->json([
                    'success' => false,
                    'predicted_price' => 0,
                    'formatted_price' => 'Modèle non disponible'
                ], 404);
            }

            // Load model only once per request
            if ($this->cachedPredictor === null) {
                $predictor->loadModel($modelPath);
                $this->cachedPredictor = $predictor;
            }

            $appartement = new Appartement();
            $appartement->setSuperficie($superficie);
            $appartement->setTypeA((string) $data['type']);

            $predictedPrice = $this->cachedPredictor->predict($appartement);
            $predictedPrice = is_numeric($predictedPrice) ? max(0, (float) $predictedPrice) : 0;

            $formattedPrice = number_format($predictedPrice, 0, ',', ' ') . ' TND';

            return $this->json([
                'success' => true,
                'predicted_price' => $predictedPrice,
                'formatted_price' => $formattedPrice
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'predicted_price' => 0,
                'formatted_price' => 'Erreur de calcul'
            ], 500);
        }
    }

    #[Route('/appartement/{id}/predict-maintenance', name: 'app_appartement_predict_maintenance', methods: ['POST'])]
    public function predictMaintenance(
        int $id,
        AppartementRepository $appartementRepository,
        MaintenancePrediction $predictor,
        EntityManagerInterface $em
    ): JsonResponse {
        $appartement = $appartementRepository->find($id);

        if (!$appartement) {
            return $this->json([
                'success' => false,
                'message' => 'Appartement non trouvé'
            ], 404);
        }

        $maintenance = $appartement->getMaintenance();
        if (!$maintenance) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun enregistrement de maintenance'
            ], 404);
        }

        if (!$this->isMaintenanceSufficientlyFilled($maintenance)) {
            return $this->json([
                'success' => false,
                'message' => 'Données insuffisantes pour une analyse',
                'showLink' => true
            ]);
        }

        try {
            $recommendation = $predictor->predict($appartement);
            $maintenance->setRecommendationIa($recommendation);
            $em->flush();

            return $this->json([
                'success' => true,
                'prediction' => $recommendation
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la prédiction: ' . $e->getMessage(),
                'showLink' => true
            ], 500);
        }
    }

    private function isMaintenanceSufficientlyFilled(\App\Entity\Residence\Maintenance $maintenance): bool
    {
        $fields = [
            $maintenance->getEtatApp(),
            $maintenance->getEtatPlomberie(),
            $maintenance->getEtatElectricite(),
            $maintenance->getEtatChauffage(),
            $maintenance->getDateDerniereMaintenance(),
            $maintenance->getDescriptionMaint(),
        ];
        $filled = array_filter($fields, fn($v) => $v !== null && $v !== '');
        return count($filled) >= 4;
    }
}
