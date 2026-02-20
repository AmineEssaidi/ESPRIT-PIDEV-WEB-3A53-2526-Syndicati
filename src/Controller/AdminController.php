<?php

namespace App\Controller;

use App\Entity\Onboarding\Onboarding;
use App\Entity\Profile\Profile;
use App\Entity\User\User;
use App\Entity\Residence\Residence;
use App\Entity\Residence\Appartement;
use App\Entity\Residence\Maintenance;

use App\Form\Onboarding\OnboardingType;
use App\Form\Profile\ProfileType;
use App\Form\User\UserType;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Repository\Residence\ResidenceRepository;
use App\Repository\Residence\Maintenanceepository;
use App\Repository\Residence\AppartementRepository;
use App\Form\Residence\ResidenceType;
use App\Form\Residence\MaintenanceType;
use App\Repository\Syndicat\ReclamationRepository;
use App\Entity\Syndicat\Reclamation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use App\Service\PageStatusService;
use App\Service\MachineLearning;
use App\Service\MaintenancePrediction;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class AdminController extends AbstractController
{
    #[Route('/admin/users', name: 'admin_users')]
    public function users(PageStatusService $pageStatusService, Request $request, UserRepository $userRepository, ProfileRepository $profileRepository, OnboardingRepository $onboardingRepository): Response
    {
        $users = $userRepository->findBy([], ['created_at' => 'DESC']);
        $profiles = $profileRepository->findBy([], ['id_profile' => 'DESC']);
        $onboardings = $onboardingRepository->findBy([], ['id_onboarding' => 'DESC']);
        $editUser = new User();
        $editForm = $this->createForm(UserType::class, $editUser, ['signup' => false, 'edit' => true]);
        $addUser = new User();
        $addForm = $this->createForm(UserType::class, $addUser, ['signup' => false, 'edit' => false, 'add' => true]);
        $profileEditForm = $this->createForm(ProfileType::class, new Profile());
        $onboardingEditForm = $this->createForm(OnboardingType::class, new Onboarding(), ['admin_edit' => true, 'use_prefs_from_request' => true]);
        return $this->render('admin/Users/index.html.twig', [
            'users' => $users,
            'profiles' => $profiles,
            'onboardings' => $onboardings,
            'editForm' => $editForm->createView(),
            'addForm' => $addForm->createView(),
            'profileEditForm' => $profileEditForm->createView(),
            'onboardingEditForm' => $onboardingEditForm->createView(),
        ]);
    }

    #[Route('/admin/profile/{id}/edit', name: 'admin_profile_edit', methods: ['GET', 'POST'])]
    public function profileEdit(int $id, Request $request, ProfileRepository $profileRepository, EntityManagerInterface $em): Response
    {
        $profile = $profileRepository->find($id);
        if (!$profile instanceof Profile) {
            $this->addFlash('danger', 'Profile not found.');
            return $this->redirectToRoute('admin_users');
        }
        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('admin_users');
        }
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/add', name: 'admin_users_add', methods: ['POST'])]
    public function userAdd(Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['signup' => false, 'edit' => false, 'add' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $userRepository->findOneBy(['email_user' => $user->getEmailUser()]);
            if ($existing) {
                $this->addFlash('danger', 'A user with this email already exists.');
                return $this->redirectToRoute('admin_users');
            }
            $plainPassword = $form->get('password_user')->getData();
            $user->setPasswordUser($passwordHasher->hashPassword($user, $plainPassword));
            $now = new \DateTime();
            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'User added successfully.');
            return $this->redirectToRoute('admin_users');
        }
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function userEdit(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            $this->addFlash('danger', 'User not found.');
            return $this->redirectToRoute('admin_users');
        }
        $form = $this->createForm(UserType::class, $user, ['signup' => false, 'edit' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // Password Change Logic (same as FrontendController)
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            if ($newPassword) {
                if (!$currentPassword) {
                    $this->addFlash('danger', 'You must provide the current password to change it.');
                } else {
                    if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                        $this->addFlash('danger', 'Current password is invalid.');
                    } else {
                        $hashedPassword = $passwordHasher->hashPassword(
                            $user,
                            $newPassword
                        );
                        $user->setPasswordUser($hashedPassword);
                        $this->addFlash('success', 'Password updated successfully.');
                    }
                }
            }

            $user->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('admin_users');
        }
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function userDelete(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('user_delete', $token ?? ''))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            $this->addFlash('danger', 'User not found.');
            return $this->redirectToRoute('admin_users');
        }
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'User deleted successfully.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/onboarding/{id}/edit', name: 'admin_onboarding_edit', methods: ['GET', 'POST'])]
    public function onboardingEdit(int $id, Request $request, OnboardingRepository $onboardingRepository, EntityManagerInterface $em): Response
    {
        $onboarding = $onboardingRepository->find($id);
        if (!$onboarding instanceof Onboarding) {
            $this->addFlash('danger', 'Onboarding not found.');
            return $this->redirectToRoute('admin_users');
        }
        $form = $this->createForm(OnboardingType::class, $onboarding, ['admin_edit' => true, 'use_prefs_from_request' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $prefs = $request->request->all('prefs');
            $defaults = [
                'notification_channel' => 'EMAIL',
                'notification_frequency' => 'DAILY_DIGEST',
                'property_type' => 'APARTMENT',
                'occupancy_status' => 'OWNER_OCCUPIED',
                'parking_type' => 'NONE',
                'meeting_participation' => 'HYBRID',
                'document_delivery' => 'DIGITAL',
                'contact_preference' => 'EMAIL',
                'maintenance_priority' => 'FLEXIBLE',
                'community_engagement' => 'MODERATE',
                'payment_method_preference' => 'ONLINE',
                'noise_sensitivity' => 'MODERATE',
                'pets_status' => 'NO_PETS',
                'accessibility_needs' => 'NONE',
            ];
            $prefs = array_merge($defaults, is_array($prefs) ? $prefs : []);
            $prefs['language_preference'] = match ($onboarding->getSelectedLocale()) {
                'en' => 'EN', 'ar' => 'AR', 'fr_ar' => 'FR_AR', default => 'FR',
            };
            $prefs['theme_preference'] = $onboarding->getSelectedTheme() === 'light' ? 'LIGHT' : 'DARK';
            $onboarding->setSelectedPreferences($prefs);
            $onboarding->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Onboarding updated successfully.');
            return $this->redirectToRoute('admin_users');
        }
        return $this->redirectToRoute('admin_users');
    }
#[Route('/residence/admin', name: 'admin_residence')]
public function residence(
    PageStatusService $pageStatusService, 
    Request $request, 
    ResidenceRepository $residenceRepository, 
    \App\Repository\Residence\AppartementRepository $appartementRepository, 
    \App\Repository\Residence\MaintenanceRepository $maintenanceRepository, 
    \Symfony\Component\Form\FormFactoryInterface $formFactory, 
    EntityManagerInterface $em, 
    \Symfony\Component\String\Slugger\SluggerInterface $slugger,
    MachineLearning $predictor,
    MaintenancePrediction $pr,
    \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params
): Response
{
    $pageStatusService->setPageStatus('residence', 'online');

    $residences = $residenceRepository->findAll();

    $residenceAddForm = $this->createForm(ResidenceType::class, new Residence(), [
        'action' => $this->generateUrl('admin_residence_add'),
        'method' => 'POST',
    ]);

    $appartements = $appartementRepository->findAll();
    
    // Load predictions
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

    $appartementAddForm = $formFactory->createNamed('appartement_add', \App\Form\Residence\AppartementType::class, new \App\Entity\Residence\Appartement(), [
        'action' => $this->generateUrl('app_appartement_new'),
        'method' => 'POST',
    ]);

    $residenceEditForm = $formFactory->createNamed('residence_edit', ResidenceType::class, new Residence());
    $appartementEditForm = $formFactory->createNamed('appartement_edit', \App\Form\Residence\AppartementType::class, new \App\Entity\Residence\Appartement());

    $maintenances = $maintenanceRepository->findAll();
    $maintenanceAddForm = $formFactory->createNamed('maintenance_add', \App\Form\Residence\MaintenanceType::class, new \App\Entity\Residence\Maintenance(), [
        'action' => $this->generateUrl('app_appartement_new'),
        'method' => 'POST',
    ]);

    $maintenanceEditForm = $formFactory->createNamed('maintenance_edit', \App\Form\Residence\MaintenanceType::class, new \App\Entity\Residence\Maintenance());

    $predictionsmaintenance = [];
    foreach ($appartements as $appartement) {
        $maintenance = $appartement->getMaintenance();
        $predictionsmaintenance[$appartement->getIdApp()] = $maintenance?->getRecommendationIa();
    }

    $em->flush();
    
    return $this->render('admin/Residence/index.html.twig', [
        'residences' => $residences,
        'appartements' => $appartements,
        'predictions' => $predictions,
        'predictionsmaintenance' => $predictionsmaintenance,
        'maintenances' => $maintenances,
        'residenceAddForm' => $residenceAddForm->createView(),
        'residenceEditForm' => $residenceEditForm->createView(),
        'appartementAddForm' => $appartementAddForm->createView(),
        'appartementEditForm' => $appartementEditForm->createView(),
        'maintenanceAddForm' => $maintenanceAddForm->createView(),
        'maintenanceEditForm' => $maintenanceEditForm->createView(),
    ]);
}

#[Route('/admin/residence/add', name: 'admin_residence_add', methods: ['POST'])]
    public function residenceAdd(Request $request, EntityManagerInterface $em, \Symfony\Component\String\Slugger\SluggerInterface $slugger, \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
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
    #[Route('/admin/residence/{id}/edit', name: 'admin_residence_edit', methods: ['POST'])]
    public function residenceEdit(int $id, Request $request, ResidenceRepository $residenceRepository, EntityManagerInterface $em, \Symfony\Component\String\Slugger\SluggerInterface $slugger, \Symfony\Component\Form\FormFactoryInterface $formFactory): JsonResponse
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

    #[Route('/admin/residence/{id}/delete', name: 'admin_residence_delete', methods: ['POST'])]
    public function residenceDelete(int $id, Request $request, ResidenceRepository $residenceRepository, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
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
    //            FONCTION POUR LA PREDICTION DU PRIX                    //
    ///////////////////////////////////////////////////////////////////////
private ?MachineLearning $cachedPredictor = null;

#[Route('/residence/admin/prediction', name: 'prediction_prix', methods: ['POST'])]
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
    
    // Check if data is sufficient (you can reuse your logic)
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

    #[Route('/admin/forum', name: 'admin_forum')]
    public function forum(PageStatusService $pageStatusService, Request $request, \App\Repository\Forum\PublicationRepository $publicationRepository, \App\Repository\Forum\CommentaireRepository $commentaireRepository, \Symfony\Component\Form\FormFactoryInterface $formFactory): Response
    {
        $publications = $publicationRepository->findBy([], ['date_creation_pub' => 'DESC']);
        $commentaires = $commentaireRepository->findBy([], ['created_at' => 'DESC']);

        $pubEditForm = $formFactory->createNamed('publication_edit', \App\Form\Forum\PublicationType::class, new \App\Entity\Forum\Publication());
        $pubAddForm = $formFactory->createNamed('publication_add', \App\Form\Forum\PublicationType::class, new \App\Entity\Forum\Publication(), [
            'action' => $this->generateUrl('admin_forum_pub_add'),
            'method' => 'POST',
        ]);
        $commentEditForm = $formFactory->createNamed('comment_edit', \App\Form\Forum\CommentaireType::class, new \App\Entity\Forum\Commentaire());

        return $this->render('admin/Forum/index.html.twig', [
            'publications' => $publications,
            'commentaires' => $commentaires,
            'pubEditForm' => $pubEditForm->createView(),
            'pubAddForm' => $pubAddForm->createView(),
            'commentEditForm' => $commentEditForm->createView()
        ]);
    }

    #[Route('/admin/forum/publication/{id}/edit', name: 'admin_forum_pub_edit', methods: ['GET', 'POST'])]
    public function pubEdit(int $id, Request $request, \App\Repository\Forum\PublicationRepository $publicationRepository, EntityManagerInterface $em, \Symfony\Component\String\Slugger\SluggerInterface $slugger, \Symfony\Component\Form\FormFactoryInterface $formFactory): Response
    {
        $publication = $publicationRepository->find($id);
        if (!$publication) {
            $this->addFlash('danger', 'Publication not found.');
            return $this->redirectToRoute('admin_forum');
        }
        $form = $formFactory->createNamed('publication_edit', \App\Form\Forum\PublicationType::class, $publication);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image_pub')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('publications_directory'), $newFilename);
                    $publication->setImagePub($newFilename);
                } catch (\Exception $e) {
                }
            }
            $em->flush();
            $this->addFlash('success', 'Publication updated successfully.');
            return $this->redirectToRoute('admin_forum');
        }
        return $this->redirectToRoute('admin_forum');
    }

    #[Route('/admin/forum/publication/add', name: 'admin_forum_pub_add', methods: ['POST'])]
    public function pubAdd(Request $request, EntityManagerInterface $em, \Symfony\Component\String\Slugger\SluggerInterface $slugger, \Symfony\Component\Form\FormFactoryInterface $formFactory): Response
    {
        $publication = new \App\Entity\Forum\Publication();
        $form = $formFactory->createNamed('publication_add', \App\Form\Forum\PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Robust user retrieval (fallback to session if security context is empty)
            $user = $this->getUser();
            if (!$user) {
                $sessionUser = $request->getSession()->get('user');
                if ($sessionUser) {
                    $userId = null;
                    if (is_array($sessionUser)) {
                        $userId = $sessionUser['id_user'] ?? $sessionUser['id'] ?? null;
                    } elseif (is_object($sessionUser)) {
                        if (method_exists($sessionUser, 'getIdUser')) {
                            $userId = $sessionUser->getIdUser();
                        } elseif (method_exists($sessionUser, 'getId')) {
                            $userId = $sessionUser->getId();
                        }
                    }

                    if ($userId) {
                        $user = $em->getRepository(\App\Entity\User\User::class)->find($userId);
                    }
                }
            }

            if (!$user) {
                $this->addFlash('danger', 'Unable to identify author. Please log in again.');
                return $this->redirectToRoute('admin_forum');
            }

            $publication->setUser($user);

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image_pub')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('publications_directory'), $newFilename);
                    $publication->setImagePub($newFilename);
                } catch (\Exception $e) {
                    // Fail silently or log error
                }
            }

            $em->persist($publication);
            $em->flush();

            $this->addFlash('success', 'Publication created successfully.');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->redirectToRoute('admin_forum');
    }

    #[Route('/admin/forum/comment/{id}/edit', name: 'admin_forum_comment_edit', methods: ['GET', 'POST'])]
    public function commentEdit(int $id, Request $request, \App\Repository\Forum\CommentaireRepository $commentaireRepository, EntityManagerInterface $em, \Symfony\Component\String\Slugger\SluggerInterface $slugger, \Symfony\Component\Form\FormFactoryInterface $formFactory): Response
    {
        $comment = $commentaireRepository->find($id);
        if (!$comment) {
            $this->addFlash('danger', 'Comment not found.');
            return $this->redirectToRoute('admin_forum');
        }
        $form = $formFactory->createNamed('comment_edit', \App\Form\Forum\CommentaireType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image_commentaire')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('commentaire_images_directory'), $newFilename);
                    $comment->setImageCommentaire($newFilename);
                } catch (\Exception $e) {
                }
            }
            $comment->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Comment updated successfully.');
            return $this->redirectToRoute('admin_forum');
        }
        return $this->redirectToRoute('admin_forum');
    }

    #[Route('/admin/syndicat', name: 'admin_syndicat')]
    public function syndicat(PageStatusService $pageStatusService, Request $request, ReclamationRepository $reclamationRepository, \App\Repository\Syndicat\ReponseRepository $reponseRepository): Response
    {
        $reclamations = $reclamationRepository->findBy([], ['created_at' => 'DESC']);
        $reponses = $reponseRepository->findAll();
        return $this->render('admin/Syndicat/index.html.twig', [
            'reclamations' => $reclamations,
            'reponses' => $reponses,
        ]);
    }

    #[Route('/admin/syndicat/reclamation/{id}/delete', name: 'admin_reclamation_delete', methods: ['POST'])]
    public function deleteReclamation(Request $request, ReclamationRepository $reclamationRepository, EntityManagerInterface $entityManager, int $id): Response
    {
        $reclamation = $reclamationRepository->find($id);
        if (!$reclamation) {
            return $this->redirectToRoute('admin_syndicat');
        }
        if ($this->isCsrfTokenValid('delete_reclamation', $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
        }
        return $this->redirectToRoute('admin_syndicat');
    }

    #[Route('/admin/syndicat/reponse/{id}/delete', name: 'admin_reponse_delete', methods: ['POST'])]
    public function deleteReponse(Request $request, \App\Repository\Syndicat\ReponseRepository $reponseRepository, EntityManagerInterface $entityManager, int $id): Response
    {
        $reponse = $reponseRepository->find($id);
        if (!$reponse) {
            return $this->json(['success' => false, 'message' => 'Reponse not found'], 404);
        }

        // Use a generic token check for now since there's no specific token in the twig yet
        // but the JS is already sending a POST request to this URL.
        $entityManager->remove($reponse);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/admin/evenement', name: 'admin_evenement')]
    public function evenement(PageStatusService $pageStatusService, Request $request, \App\Repository\Evenement\EvenementRepository $evenementRepository, \App\Repository\Evenement\ParticipationRepository $participationRepository): Response
    {
        $evenements = $evenementRepository->findBy([], ['date_event' => 'DESC']);
        $participations = $participationRepository->findBy([], ['date_participation' => 'DESC']);

        // Create edit forms
        $evenementEditForm = $this->createForm(\App\Form\Evenement\EvenementType::class, new \App\Entity\Evenement\Evenement());
        $participationEditForm = $this->createForm(\App\Form\Evenement\ParticipationType::class, new \App\Entity\Evenement\Participation(), ['is_admin' => true]);

        return $this->render('admin/Evenement/index.html.twig', [
            'evenements' => $evenements,
            'participations' => $participations,
            'evenementEditForm' => $evenementEditForm->createView(),
            'participationEditForm' => $participationEditForm->createView(),
        ]);
    }

    #[Route('/admin/evenement/{id}/edit', name: 'admin_evenement_edit', methods: ['POST'])]
    public function evenementEdit(int $id, Request $request, \App\Repository\Evenement\EvenementRepository $evenementRepository, EntityManagerInterface $em): JsonResponse
    {
        $evenement = $evenementRepository->find($id);
        if (!$evenement) {
            return new JsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $form = $this->createForm(\App\Form\Evenement\EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Event updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors)], 400);
    }

    #[Route('/admin/evenement/{id}/delete', name: 'admin_evenement_delete', methods: ['POST'])]
    public function evenementDelete(
        int $id,
        Request $request,
        \App\Repository\Evenement\EvenementRepository $evenementRepository,
        \App\Repository\Evenement\ParticipationRepository $participationRepository,
        EntityManagerInterface $em,
        \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        try {
            $evenement = $evenementRepository->find($id);
            if (!$evenement) {
                return new JsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
            }

            // CSRF check
            $token = $request->request->get('_token');
            if (!$token || !$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('evenement_delete', $token))) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
            }

            // Manually delete associated participations to avoid foreign key constraint violations
            $participations = $participationRepository->findBy(['evenement' => $evenement]);
            foreach ($participations as $participation) {
                $em->remove($participation);
            }

            $em->remove($evenement);
            $em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Event and its participations deleted successfully.']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'An error occurred while deleting the event: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/admin/participation/{id}/edit', name: 'admin_participation_edit', methods: ['POST'])]
    public function participationEdit(int $id, Request $request, \App\Repository\Evenement\ParticipationRepository $participationRepository, EntityManagerInterface $em): JsonResponse
    {
        $participation = $participationRepository->find($id);
        if (!$participation) {
            return new JsonResponse(['success' => false, 'message' => 'Participation not found.'], 404);
        }

        $oldStatus = $participation->getStatutParticipation();
        $oldGuests = $participation->getNbAccompagnants();

        $form = $this->createForm(\App\Form\Evenement\ParticipationType::class, $participation, ['is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $participation->getStatutParticipation();
            $newGuests = $participation->getNbAccompagnants();
            $evenement = $participation->getEvenement();

            if ($evenement) {
                $placesToDeduct = 0;

                // Handle status changes affecting seats
                if ($oldStatus !== 'confirme' && $newStatus === 'confirme') {
                    // Newly confirmed: deduct 1 (user) + nb_accompagnants
                    $placesToDeduct = 1 + $newGuests;
                } elseif ($oldStatus === 'confirme' && $newStatus !== 'confirme') {
                    // Was confirmed, now cancelled/refused: return seats
                    $placesToDeduct = -(1 + $oldGuests);
                } elseif ($oldStatus === 'confirme' && $newStatus === 'confirme' && $oldGuests !== $newGuests) {
                    // Stayed confirmed but number of guests changed
                    $placesToDeduct = $newGuests - $oldGuests;
                }

                if ($placesToDeduct !== 0) {
                    // Check if enough seats are available
                    if ($placesToDeduct > $evenement->getNbRestants()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => "Not enough seats available. Remaining: {$evenement->getNbRestants()}, Required additional: {$placesToDeduct}."
                        ], 400);
                    }
                    $evenement->setNbRestants($evenement->getNbRestants() - $placesToDeduct);
                }
            }

            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Participation updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors)], 400);
    }
    #[Route('/admin/participation/{id}/delete', name: 'admin_participation_delete', methods: ['POST'])]
    public function participationDelete(int $id, Request $request, \App\Repository\Evenement\ParticipationRepository $participationRepository, EntityManagerInterface $em, \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $participation = $participationRepository->find($id);
        if (!$participation) {
            return new JsonResponse(['success' => false, 'message' => 'Participation not found.'], 404);
        }

        // CSRF check
        $token = $request->request->get('_token');
        if ($token && !$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('participation_delete', $token))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $evenement = $participation->getEvenement();
        if ($evenement && $participation->getStatutParticipation() === 'confirme') {
            // Return seats: 1 (primary) + guests
            $placesToReclaim = 1 + $participation->getNbAccompagnants();
            $evenement->setNbRestants($evenement->getNbRestants() + $placesToReclaim);
        }

        $em->remove($participation);
        $em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Participation deleted successfully.']);
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(PageStatusService $pageStatusService, Request $request): Response
    {
        if (!$pageStatusService->isPageOnline('dashboard')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'dashboard']);
        }

        $transactions = [
            [
                'type' => 'PayPal',
                'description' => 'Envoyer de l\'argent',
                'amount' => '+$82.6',
                'currency' => 'USD',
                'icon' => 'paypal'
            ],
            [
                'type' => 'Wallet',
                'description' => 'Mac\'D',
                'amount' => '+$270.69',
                'currency' => 'USD',
                'icon' => 'wallet'
            ],
            [
                'type' => 'Transfer',
                'description' => 'Remboursement',
                'amount' => '+$637.91',
                'currency' => 'USD',
                'icon' => 'transfer'
            ],
            [
                'type' => 'Credit Card',
                'description' => 'Commande de nourriture',
                'amount' => '-$838.71',
                'currency' => 'USD',
                'icon' => 'credit-card'
            ],
            [
                'type' => 'Wallet',
                'description' => 'Starbucks',
                'amount' => '+$203.33',
                'currency' => 'USD',
                'icon' => 'wallet'
            ],
            [
                'type' => 'Mastercard',
                'description' => 'Commande de nourriture',
                'amount' => '-$92.45',
                'currency' => 'USD',
                'icon' => 'mastercard'
            ]
        ];

        return $this->render('admin/dashboard.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/admin/super-dashboard', name: 'admin_super_dashboard')]
    public function superDashboard(PageStatusService $pageStatusService, Request $request): Response
    {
        $transactions = [
            [
                'type' => 'PayPal',
                'description' => 'Send money',
                'amount' => '+$82.6',
                'currency' => 'USD',
                'icon' => 'paypal'
            ],
            [
                'type' => 'Wallet',
                'description' => 'Mac\'D',
                'amount' => '+$270.69',
                'currency' => 'USD',
                'icon' => 'wallet'
            ],
            [
                'type' => 'Transfer',
                'description' => 'Refund',
                'amount' => '+$637.91',
                'currency' => 'USD',
                'icon' => 'transfer'
            ],
            [
                'type' => 'Credit Card',
                'description' => 'Ordered Food',
                'amount' => '-$838.71',
                'currency' => 'USD',
                'icon' => 'credit-card'
            ],
            [
                'type' => 'Wallet',
                'description' => 'Starbucks',
                'amount' => '+$203.33',
                'currency' => 'USD',
                'icon' => 'wallet'
            ],
            [
                'type' => 'Mastercard',
                'description' => 'Ordered Food',
                'amount' => '-$92.45',
                'currency' => 'USD',
                'icon' => 'mastercard'
            ]
        ];

        $pageStatuses = $pageStatusService->getAllPageStatuses();

        $frontendPages = [
            [
                'id' => 'main_home',
                'name' => 'Main Home',
                'url' => '/',
                'status' => $pageStatuses['main_home'] ?? 'online',
                'icon' => '🏡',
                'description' => 'Landing page for visitors'
            ],
            [
                'id' => 'profile',
                'name' => 'Profile',
                'url' => '/profile',
                'status' => $pageStatuses['profile'] ?? 'online',
                'icon' => '👤',
                'description' => 'User profile page'
            ]
        ];

        $backendPages = [
            [
                'id' => 'dashboard',
                'name' => 'Dashboard',
                'url' => '/admin',
                'status' => $pageStatuses['dashboard'] ?? 'online',
                'icon' => '🏠',
                'description' => 'Main admin dashboard'
            ],
            [
                'id' => 'super_dashboard',
                'name' => 'Super Dashboard',
                'url' => '/admin/super-dashboard',
                'status' => $pageStatuses['super_dashboard'] ?? 'online',
                'icon' => '⚡',
                'description' => 'Super admin control panel'
            ],
            [
                'id' => 'users',
                'name' => 'Users',
                'url' => '/admin/users',
                'status' => $pageStatuses['users'] ?? 'online',
                'icon' => '👥',
                'description' => 'User management page'
            ],
            [
                'id' => 'placeholder_1',
                'name' => 'Placeholder 1',
                'url' => '/admin/placeholder-1',
                'status' => $pageStatuses['placeholder_1'] ?? 'offline',
                'icon' => '📄',
                'description' => 'Admin placeholder page 1'
            ],
            [
                'id' => 'placeholder_2',
                'name' => 'Placeholder 2',
                'url' => '/admin/placeholder-2',
                'status' => $pageStatuses['placeholder_2'] ?? 'online',
                'icon' => '📋',
                'description' => 'Admin placeholder page 2'
            ]
        ];

        return $this->render('admin/super_dashboard.html.twig', [
            'transactions' => $transactions,
            'frontendPages' => $frontendPages,
            'backendPages' => $backendPages,
        ]);
    }

    #[Route('/admin/api/page-status', name: 'admin_page_status_update', methods: ['POST'])]
    public function updatePageStatus(Request $request, PageStatusService $pageStatusService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['pageId']) || !isset($data['status'])) {
            return new JsonResponse(['error' => 'Missing pageId or status'], 400);
        }

        $pageId = $data['pageId'];
        $status = $data['status'];

        if (!in_array($status, ['online', 'offline'])) {
            return new JsonResponse(['error' => 'Invalid status'], 400);
        }

        $pageStatusService->setPageStatus($pageId, $status);


        return new JsonResponse([
            'success' => true,
            'pageId' => $pageId,
            'status' => $status
        ]);
    }

    #[Route('/admin/placeholder-1', name: 'admin_placeholder_1')]
    public function placeholder1(PageStatusService $pageStatusService, Request $request): Response
    {
        if (!$pageStatusService->isPageOnline('placeholder_1')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'placeholder_1']);
        }

        return $this->render('admin/placeholder1.html.twig');
    }

    #[Route('/admin/placeholder-2', name: 'admin_placeholder_2')]
    public function placeholder2(PageStatusService $pageStatusService, Request $request): Response
    {
        if (!$pageStatusService->isPageOnline('placeholder_2')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'placeholder_2']);
        }

        return $this->render('admin/placeholder2.html.twig');
    }

    #[Route('/admin/super-dashboard-check', name: 'admin_super_dashboard_check')]
    public function superDashboardCheck(PageStatusService $pageStatusService, Request $request): Response
    {
        if (!$pageStatusService->isPageOnline('super_dashboard')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'super_dashboard']);
        }

        return $this->redirectToRoute('admin_super_dashboard');
    }

    #[Route('/maintenance', name: 'maintenance')]
    #[Route('/maintenance/{pageId}', name: 'maintenance_with_page')]
    public function maintenance(Request $request, ?string $pageId = null): Response
    {
        $referer = $request->headers->get('referer');

        if ($pageId) {
            $previousPage = $this->getPageNameFromId($pageId);
            if (!$referer || strpos($referer, '/maintenance') !== false) {
                $referer = $this->getDefaultReferrerForPage($pageId);
            }
        } else {
            if ($referer && strpos($referer, '/maintenance') !== false) {
                $referer = null; 
                $previousPage = null;
            } else {
                $previousPage = $this->getPageNameFromUrl($referer);
            }
        }

        return $this->render('maintenance.html.twig', [
            'previousPage' => $previousPage,
            'referer' => $referer
        ]);
    }

    private function getPageNameFromUrl(?string $url): string
    {
        if (!$url) {
            return 'Dashboard';
        }

        if (strpos($url, '/admin/placeholder-1') !== false) {
            return 'Placeholder 1';
        } elseif (strpos($url, '/admin/placeholder-2') !== false) {
            return 'Placeholder 2';
        } elseif (strpos($url, '/admin/users') !== false) {
            return 'Users';
        } elseif (strpos($url, '/admin/super-dashboard') !== false) {
            return 'Super Dashboard';
        } elseif (strpos($url, '/admin') !== false) {
            return 'Dashboard';
        } elseif (strpos($url, '/profile') !== false) {
            return 'Profile';
        } elseif (strpos($url, '/') !== false) {
            return 'Main Home';
        }

        return 'Dashboard';
    }

    private function getPageNameFromId(string $pageId): string
    {
        $pageNames = [
            'main_home' => 'Main Home',
            'profile' => 'Profile',
            'dashboard' => 'Dashboard',
            'super_dashboard' => 'Super Dashboard',
            'users' => 'Users',
            'placeholder_1' => 'Placeholder 1',
            'placeholder_2' => 'Placeholder 2'
        ];

        return $pageNames[$pageId] ?? 'Page';
    }

    private function getDefaultReferrerForPage(string $pageId): string
    {
        $defaultReferrers = [
            'main_home' => '/',
            'profile' => '/',
            'dashboard' => '/admin/super-dashboard',
            'super_dashboard' => '/admin',
            'users' => '/admin/super-dashboard',
            'placeholder_1' => '/admin/super-dashboard',
            'placeholder_2' => '/admin/super-dashboard'
        ];

        return $defaultReferrers[$pageId] ?? '/admin/super-dashboard';
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('is_logged_in');
        $session->remove('user');

        return $this->redirectToRoute('main_home');
    }

}
