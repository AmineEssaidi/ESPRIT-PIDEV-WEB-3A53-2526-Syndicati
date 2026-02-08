<?php

namespace App\Controller;

use App\Entity\Onboarding\Onboarding;
use App\Entity\Profile\Profile;
use App\Entity\User\User;
use App\Form\Onboarding\OnboardingType;
use App\Form\Profile\ProfileType;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Repository\Syndicat\ReclamationRepository;
use App\Service\PageStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FrontendController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    #[Route('/', name: 'main_home')]
    public function mainHome(PageStatusService $pageStatusService, Request $request, UserRepository $userRepository, OnboardingRepository $onboardingRepository, EntityManagerInterface $em): Response
    {
        // Check if main home is offline
        if (!$pageStatusService->isPageOnline('main_home')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'main_home']);
        }

        $session = $request->getSession();
        $user = null;
        $onboarding = null;
        $showOverlay = false;
        $prefs = [];

        if ($session->get('is_logged_in') && $session->get('user') && isset($session->get('user')['id'])) {
            $user = $userRepository->find((int) $session->get('user')['id']);
            if ($user) {
                $onboarding = $onboardingRepository->findOneByUser($user);
                if ($onboarding === null) {
                    // First time we ever see this user on main home: create onboarding record
                    $onboarding = new Onboarding();
                    $onboarding->setUser($user);
                    $onboarding->setStep(1);
                    $onboarding->setStartedAt(new \DateTime());
                    $onboarding->setUpdatedAt(new \DateTime());
                    $em->persist($onboarding);
                    $em->flush();
                }

                // Show overlay only once per browser session, on the first visit after signup
                $alreadyShownThisSession = $session->get('onboarding_overlay_shown', false);
                if (!$onboarding->isCompleted() && !$alreadyShownThisSession) {
                    $showOverlay = true;
                    $prefs = $onboarding->getSelectedPreferences() ?? [];
                    $session->set('onboarding_overlay_shown', true);
                }
            }
        }

        $response = $this->render('frontend/home/main-home.html.twig', [
            'user' => $user,
            'onboarding' => $onboarding,
            'prefs' => $prefs,
            'show_onboarding_overlay' => $showOverlay,
        ]);

        // Ajouter des en-têtes de cache HTTP pour de meilleures performances
        $response->setPublic();
        $response->setMaxAge(300); // 5 minutes
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setEtag(md5($response->getContent()));

        return $response;
    }

    #[Route('/profile', name: 'frontend_profile', methods: ['GET', 'POST'])]
    public function profile(
        PageStatusService $pageStatusService,
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        OnboardingRepository $onboardingRepository,
        ReclamationRepository $reclamationRepository,
        \App\Repository\Forum\PublicationRepository $publicationRepository,
        \App\Repository\Evenement\EvenementRepository $evenementRepository,
        EntityManagerInterface $em,
        CacheInterface $cache
    ): Response {
        if (!$pageStatusService->isPageOnline('profile')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'profile']);
        }

        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            $this->addFlash('danger', 'Please sign in to view your profile.');
            return $this->redirectToRoute('auth_sign_in');
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);
        if (!$user) {
            $session->remove('is_logged_in');
            $session->remove('user');
            $this->addFlash('danger', 'User not found.');
            return $this->redirectToRoute('auth_sign_in');
        }

        $profile = $profileRepository->findOneByUser($user);
        if (!$profile) {
            $profile = new Profile();
            $profile->setUser($user);
            $em->persist($profile);
            $em->flush();
        }

        $onboarding = $onboardingRepository->findOneByUser($user);
        if ($onboarding !== null) {
            $needsFlush = false;
            if ($profile->getLocale() === null || $profile->getLocale() === '') {
                $profile->setLocale($onboarding->getSelectedLocale());
                $needsFlush = true;
            }
            if ($profile->getTheme() === null) {
                $profile->setTheme($onboarding->getSelectedTheme() === 'light' ? 1 : 0);
                $needsFlush = true;
            }
            if ($needsFlush) {
                $em->flush();
            }
        }

        $profileForm = $this->createForm(ProfileType::class, $profile);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profile updated.');
            return $this->redirectToRoute('frontend_profile');
        }

        $onboarding = $onboardingRepository->findOneByUser($user);
        $onboardingFormView = null;
        if ($onboarding !== null) {
            $onboardingFormView = $this->createForm(\App\Form\Onboarding\OnboardingType::class, $onboarding, ['admin_edit' => false, 'profile_edit' => true])->createView();
        }

        $isAdmin = in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC']);
        if ($isAdmin) {
            $reclamations = $reclamationRepository->findBy([], ['created_at' => 'DESC']);
        } else {
            $reclamations = $reclamationRepository->findBy(['user' => $user], ['created_at' => 'DESC']);
        }

        // Handle images decoding (with ultra-robust folder scanning & caching)
        $publicDir = $this->getParameter('kernel.project_dir') . '/public/reclamation_images/';

        foreach ($reclamations as $rec) {
            // Generate a unique cache key based on ID and update time
            $updatedAt = $rec->getUpdatedAt() ? $rec->getUpdatedAt()->getTimestamp() : 0;
            $cacheKey = 'rec_images_' . $rec->getId() . '_' . $updatedAt;

            $rec->decodedImages = $cache->get($cacheKey, function (ItemInterface $item) use ($rec, $publicDir) {
                // Cache for 1 week, but key change (updatedAt) will invalidate it earlier
                $item->expiresAfter(604800);

                $imgStr = $rec->getImagereclamation();
                $detectedFolders = [];

                if ($imgStr) {
                    // 1. Try JSON decode first (Cleanest)
                    $jsonDecoded = json_decode($imgStr, true);
                    if (is_array($jsonDecoded) && !empty($jsonDecoded)) {
                        foreach ($jsonDecoded as $path) {
                            // Extract folder from "Folder/File.ext"
                            $parts = explode('/', str_replace('\\', '/', $path));
                            if (count($parts) > 1) {
                                $detectedFolders[] = $parts[0];
                            }
                        }
                    }

                    // 2. Heuristic Regex (Fallback & Legacy)
                    // Matches: Name_Timestamp (allowing hyphens/underscores in timestamp)
                    preg_match_all('/([a-zA-Z0-9_\-\.]+_[0-9\-\_]{10,20})/', $imgStr, $matches);
                    if (!empty($matches[0])) {
                        $detectedFolders = array_merge($detectedFolders, $matches[0]);
                    }

                    // Fallback
                    if (empty($detectedFolders)) {
                        $raw = trim($imgStr, '[]"');
                        $segments = preg_split('/["\s]*,["\s]*/', $raw);
                        foreach ($segments as $seg) {
                            $seg = trim($seg, '"/ ');
                            if (str_contains($seg, '/')) {
                                $parts = explode('/', $seg);
                                $detectedFolders[] = $parts[0];
                            } elseif (str_contains($seg, '_202')) {
                                $detectedFolders[] = $seg;
                            }
                        }
                    }
                }

                $finalImages = [];
                foreach (array_unique($detectedFolders) as $folder) {
                    $folderPath = $publicDir . $folder;
                    if (is_dir($folderPath)) {
                        $files = scandir($folderPath);
                        foreach ($files as $f) {
                            if ($f !== '.' && $f !== '..' && is_file($folderPath . '/' . $f)) {
                                $finalImages[] = $folder . '/' . $f;
                            }
                        }
                    }
                }

                return array_unique($finalImages);
            });

            // Handle Response Images Decoding
            foreach ($rec->getReponses() as $reponse) {
                $respImgStr = $reponse->getImagereponse();
                $respDecodedImages = [];

                if ($respImgStr) {
                    $jsonDecoded = json_decode($respImgStr, true);
                    if (is_array($jsonDecoded) && !empty($jsonDecoded)) {
                        $respDecodedImages = $jsonDecoded;
                    } elseif (!str_contains($respImgStr, '[')) {
                        // Legacy single file fallback
                        // Only add if it looks like a file (simple check)
                        if (strpos($respImgStr, '.') !== false) {
                            $respDecodedImages[] = $respImgStr;
                        }
                    }
                }
                // Store in a dynamic property for Twig to access
                $reponse->decodedImages = $respDecodedImages;
            }
        }

        if ($isAdmin) {
            $publications = $publicationRepository->findAllLatest();
            $events = $evenementRepository->findAllWithUser();
        } else {
            $publications = $publicationRepository->findBy(['user' => $user], ['date_creation_pub' => 'DESC']);
            $events = $evenementRepository->findBy(['user' => $user], ['date_event' => 'DESC']);
        }

        $response = $this->render('frontend/profile/profile.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'profileForm' => $profileForm->createView(),
            'onboarding' => $onboarding,
            'onboardingForm' => $onboardingFormView,
            'reclamations' => $reclamations,
            'publications' => $publications,
            'events' => $events,
            'isAdmin' => $isAdmin,
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        return $response;
    }

    #[Route('/profile/onboarding-update', name: 'frontend_profile_onboarding_update', methods: ['POST'])]
    public function profileOnboardingUpdate(
        Request $request,
        UserRepository $userRepository,
        OnboardingRepository $onboardingRepository,
        EntityManagerInterface $em
    ): Response {
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            $this->addFlash('danger', 'Please sign in to update onboarding.');
            return $this->redirectToRoute('auth_sign_in');
        }
        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->redirectToRoute('auth_sign_in');
        }
        $onboarding = $onboardingRepository->findOneByUser($user);
        if (!$onboarding instanceof Onboarding) {
            $onboarding = new Onboarding();
            $onboarding->setUser($user);
            $onboarding->setStep(1);
            $onboarding->setStartedAt(new \DateTime());
            $em->persist($onboarding);
        }
        $form = $this->createForm(OnboardingType::class, $onboarding, ['admin_edit' => false, 'profile_edit' => true]);
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
            $this->addFlash('success', 'Onboarding choices updated.');
            return $this->redirectToRoute('frontend_profile');
        }
        $this->addFlash('danger', 'Invalid form.');
        return $this->redirectToRoute('frontend_profile');
    }

    #[Route('/profile/avatar-upload', name: 'frontend_profile_avatar_upload', methods: ['POST'])]
    public function profileAvatarUpload(
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): Response {
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            $this->addFlash('danger', 'Please sign in to upload an avatar.');
            return $this->redirectToRoute('auth_sign_in');
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);
        if (!$user) {
            $session->remove('is_logged_in');
            $session->remove('user');
            $this->addFlash('danger', 'User not found.');
            return $this->redirectToRoute('auth_sign_in');
        }

        $profile = $profileRepository->findOneByUser($user);
        if (!$profile) {
            $profile = new Profile();
            $profile->setUser($user);
            $em->persist($profile);
            $em->flush();
        }

        if (!$this->isCsrfTokenValid('profile_avatar_upload', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');
            return $this->redirectToRoute('frontend_profile');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('avatar_file');
        if (!$file || !$file->isValid()) {
            $this->addFlash('danger', 'Please choose a valid image file.');
            return $this->redirectToRoute('frontend_profile');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!\in_array($file->getMimeType(), $allowed, true)) {
            $this->addFlash('danger', 'Only JPEG, PNG, GIF and WebP images are allowed.');
            return $this->redirectToRoute('frontend_profile');
        }

        $ext = $file->guessExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'jpg';
        $safeExt = \in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? strtolower($ext) : 'jpg';
        $filename = 'avatar_' . $userId . '_' . uniqid('', true) . '.' . $safeExt;
        $dir = $projectDir . '/public/profile_images';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $file->move($dir, $filename);
        } catch (FileException $e) {
            $this->addFlash('danger', 'Could not save the image. Please try again.');
            return $this->redirectToRoute('frontend_profile');
        }

        $profile->setAvatar('profile_images/' . $filename);
        $em->flush();

        $userData = $session->get('user', []);
        $userData['avatar'] = 'profile_images/' . $filename;
        $session->set('user', $userData);

        $this->addFlash('success', 'Avatar updated.');
        return $this->redirectToRoute('frontend_profile');
    }

    #[Route('/our-team', name: 'frontend_our_team')]
    public function ourTeam(): Response
    {
        $response = $this->render('frontend/about/our-team.html.twig');

        // Contenu statique, mise en cache plus longue
        $response->setPublic();
        $response->setMaxAge(3600); // 1 hour
        $response->setEtag(md5($response->getContent()));

        return $response;
    }

    #[Route('/contact', name: 'frontend_contact')]
    public function contact(): Response
    {
        $response = $this->render('frontend/about/contact.html.twig');

        // Static content, cache longer
        $response->setPublic();
        $response->setMaxAge(3600); // 1 hour
        $response->setEtag(md5($response->getContent()));

        return $response;
    }

    #[Route('/sign-in', name: 'auth_sign_in', methods: ['GET', 'POST'])]
    public function signIn(Request $request, UserRepository $userRepository, ProfileRepository $profileRepository, OnboardingRepository $onboardingRepository, UserPasswordHasherInterface $passwordHasher): Response
    {
        $session = $request->getSession();
        $error = null;
        $lastEmail = '';

        if ($session->get('is_logged_in')) {
            return $this->redirectToRoute('main_home');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = $request->request->get('password');
            $lastEmail = $email;

            if ($email !== '' && $password !== null) {
                $user = $userRepository->findOneBy(['email_user' => $email]);
                if ($user !== null && $passwordHasher->isPasswordValid($user, $password)) {
                    if (!$user->getIsVerified()) {
                        $error = 'Your account is not yet verified by an administrator. You cannot log in until your account is verified.';
                    } else {
                        $profile = $profileRepository->findOneByUser($user);
                        $avatar = $profile?->getAvatar();

                        $defaults = [
                            'theme' => 'dark',
                            'accent-gradient' => 'linear-gradient(135deg, #6c5ce7, #8b5cf6, #06b6d4)',
                            'accent-color' => '#6c5ce7',
                            'lang' => 'fr'
                        ];
                        $userSettings = $profile ? array_merge($defaults, $profile->getSettings()) : $defaults;

                        $session->set('is_logged_in', true);
                        $session->set('user', [
                            'id' => $user->getIdUser(),
                            'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
                            'email' => $user->getEmailUser(),
                            'role' => $user->getRoleUser(),
                            'avatar' => $avatar,
                            'settings' => $userSettings,
                        ]);
                        $onboarding = $onboardingRepository->findOneByUser($user);
                        if ($onboarding === null || !$onboarding->isCompleted()) {
                            return $this->redirectToRoute('onboarding');
                        }
                        $adminRoles = ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'];
                        if (in_array($user->getRoleUser(), $adminRoles, true)) {
                            return $this->render('frontend/auth/sign-in.html.twig', [
                                'error' => null,
                                'last_email' => $lastEmail,
                                'show_destination_choice' => true,
                            ]);
                        }
                        return $this->redirectToRoute('main_home');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Please enter your email and password.';
            }
        }

        $response = $this->render('frontend/auth/sign-in.html.twig', [
            'error' => $error,
            'last_email' => $lastEmail,
        ]);
        $response->setPrivate();

        return $response;
    }

    #[Route('/sign-up', name: 'auth_sign_up', methods: ['GET', 'POST'])]
    public function signUp(Request $request): Response
    {
        // Redirect to the real signup form (Symfony form, saves to DB)
        return $this->redirectToRoute('user_signup');
    }

    #[Route('/logout', name: 'auth_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('is_logged_in');
        $session->remove('user');

        return $this->redirectToRoute('main_home', ['logout' => 'success']);
    }

    #[Route('/check-email', name: 'check_email', methods: ['GET'])]
    public function checkEmail(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $email = $request->query->get('email', '');

        if (empty($email)) {
            return new JsonResponse(['exists' => false]);
        }

        $existingUser = $em->getRepository(User::class)->findOneBy(['email_user' => $email]);

        return new JsonResponse(['exists' => $existingUser !== null]);
    }
}
