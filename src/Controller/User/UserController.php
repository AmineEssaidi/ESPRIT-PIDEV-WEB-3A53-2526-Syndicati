<?php
namespace App\Controller\User;

use App\Controller\Concerns\SessionUserAwareTrait;
use App\Entity\Onboarding\Onboarding;
use App\Entity\Profile\Profile;
use App\Entity\User\User;
use App\Form\Onboarding\OnboardingType;
use App\Form\Profile\ProfileType;
use App\Form\User\UserType;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Repository\UserRelationship\UserRelationshipRepository;
use App\Repository\Syndicat\ReclamationRepository;
use App\Service\PageStatusService;
use App\Service\UserStanding\UserStandingService;
use App\Service\Log\UserActivityLogger;
use App\Service\Media\ImageKitStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\TwoFactor\TwoFactorService;
use App\Service\FormErrorHelperTrait;

class UserController extends AbstractController
{
    use FormErrorHelperTrait;
    use SessionUserAwareTrait;
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UserStandingService $userStandingService,
        private readonly \App\Service\User\NotificationService $notifService,
        private readonly ImageKitStorageService $imageStorage
    ) {
    }

    #[Route('/signup', name: 'user_signup', methods: ['GET', 'POST'])]
    public function signup(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        HttpClientInterface $httpClient,
        #[Autowire(param: 'hcaptcha_site_key')]
        string $hcaptchaSiteKey = '',
        #[Autowire(param: 'hcaptcha_secret_key')]
        string $hcaptchaSecretKey = '',
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'signup' => true,
            'validation_groups' => ['Default', 'registration']
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

            // Verify hCaptcha if configured
            if ($hcaptchaSecretKey !== '' && $hcaptchaSiteKey !== '') {
                $captchaResponse = $request->request->get('h-captcha-response');
                if ($captchaResponse === null || trim((string) $captchaResponse) === '') {
                    $error = 'Please complete the security verification (captcha) before creating an account.';
                    if ($isAjax) {
                        return $this->json(['success' => false, 'message' => $error], 400);
                    }
                    $this->addFlash('danger', $error);
                    return $this->render('frontend/signup.html.twig', [
                        'form' => $form->createView(),
                        'hcaptcha_site_key' => $hcaptchaSiteKey,
                    ]);
                }

                $verified = $this->verifyHcaptcha($httpClient, $hcaptchaSecretKey, (string) $captchaResponse);
                if (!$verified) {
                    $error = 'Security verification failed. Please try again.';
                    if ($isAjax) {
                        return $this->json(['success' => false, 'message' => $error], 400);
                    }
                    $this->addFlash('danger', $error);
                    return $this->render('frontend/signup.html.twig', [
                        'form' => $form->createView(),
                        'hcaptcha_site_key' => $hcaptchaSiteKey,
                    ]);
                }
            }

            if ($form->isValid()) {
                $existingUser = $em->getRepository(User::class)->findOneBy(['email_user' => $user->getEmailUser()]);
                if ($existingUser) {
                    if ($isAjax) {
                        return $this->json(['success' => false, 'message' => 'This email is already registered.'], 400);
                    }
                    $this->addFlash('danger', 'This email is already registered.');
                } else {
                    $plainPassword = $user->getPlainPassword();
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPasswordUser($hashedPassword);
                    $user->setRoleUser('RESIDENT');
                    $user->setIsVerified(false);
                    $now = new \DateTime();
                    $user->setCreatedAt($now);
                    $user->setUpdatedAt($now);

                    $em->persist($user);
                    $em->flush();

                    $profile = new Profile();
                    $profile->setUser($user);
                    $em->persist($profile);
                    $em->flush();

                    if ($isAjax) {
                        return $this->json(['success' => true, 'message' => 'Account created successfully!']);
                    }

                    $this->addFlash('success', 'Account created successfully!');
                    return $this->redirectToRoute('auth_sign_in');
                }
                if ($isAjax) {
                    return $this->json(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
                }

                $this->addFlash('error_popup', implode('|', $this->getFormErrors($form)));
                $this->addFlash('danger', 'Please correct the errors in the form.');
            }
        }

        return $this->render('frontend/signup.html.twig', [
            'form' => $form->createView(),
            'hcaptcha_site_key' => $hcaptchaSiteKey,
        ]);
    }

    #[Route('/sign-up', name: 'auth_sign_up', methods: ['GET', 'POST'])]
    public function signUpRedirect(Request $request): Response
    {
        return $this->redirectToRoute('user_signup');
    }

    #[Route('/sign-in', name: 'auth_sign_in', methods: ['GET', 'POST'])]
    public function signIn(
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        OnboardingRepository $onboardingRepository,
        UserPasswordHasherInterface $passwordHasher,
        HttpClientInterface $httpClient,
        TwoFactorService $twoFactorService,
        UserActivityLogger $activityLogger,
        EntityManagerInterface $em,
        #[Autowire(param: 'hcaptcha_site_key')]
        string $hcaptchaSiteKey = '',
        #[Autowire(param: 'hcaptcha_secret_key')]
        string $hcaptchaSecretKey = '',
    ): Response {
        $session = $request->getSession();
        $error = null;
        $lastEmail = '';

        if ($session->get('is_logged_in')) {
            if ($request->query->get('destination') === 'choice') {
                return $this->render('frontend/auth/sign-in.html.twig', [
                    'error' => null,
                    'last_email' => '',
                    'show_destination_choice' => true,
                    'hcaptcha_site_key' => $hcaptchaSiteKey,
                ]);
            }
            return $this->redirectToRoute('main_home');
        }

        // After POST with error we redirect to GET (PRG) so refresh doesn't resubmit and cause reload loop
        if ($request->isMethod('GET') && $session->has('signin_error')) {
            $error = $session->get('signin_error');
            $lastEmail = (string) $session->get('signin_last_email', '');
            $session->remove('signin_error');
            $session->remove('signin_last_email');
            return $this->render('frontend/auth/sign-in.html.twig', [
                'error' => $error,
                'last_email' => $lastEmail,
                'hcaptcha_site_key' => $hcaptchaSiteKey,
            ]);
        }

        // Redirect 127.0.0.1 → localhost so WebAuthn works (browsers often reject "This is an invalid domain" on 127.0.0.1)
        if ($request->getHost() === '127.0.0.1' && $request->isMethod('GET')) {
            $port = $request->getPort();
            $scheme = $request->getScheme();
            $url = $scheme . '://localhost' . ($port && $port !== 80 && $port !== 443 ? ':' . $port : '') . $request->getRequestUri();
            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }

        // GET with pending 2FA TOTP session: show sign-in with popup so user can enter app code (e.g. after refresh)
        if ($request->isMethod('GET')) {
            $pending2faUserId = $session->get('2fa_user_id');
            $pending2faEmail = $session->get('2fa_email');
            if ($pending2faUserId && $pending2faEmail) {
                $conn = $em->getConnection();
                $twoFaRow = $conn->fetchAssociative(
                    'SELECT two_factor_enabled, totp_secret FROM user WHERE id_user = :id',
                    ['id' => $pending2faUserId],
                    ['id' => \PDO::PARAM_INT]
                );
                $totpConfigured = $twoFaRow && isset($twoFaRow['totp_secret']) && $twoFaRow['totp_secret'] !== '' && $twoFaRow['totp_secret'] !== null;
                if ($totpConfigured) {
                    $user = $userRepository->find($pending2faUserId);
                    if ($user && $user->getEmailUser() === $pending2faEmail) {
                        return $this->render('frontend/auth/sign-in.html.twig', [
                            'error' => null,
                            'last_email' => $pending2faEmail,
                            'show_totp_popup' => true,
                            'hcaptcha_site_key' => $hcaptchaSiteKey,
                        ]);
                    }
                }
            }
        }

        $isAjax = $request->isXmlHttpRequest();

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = $request->request->get('password');
            $lastEmail = $email;
            $user = null;

            // Require hCaptcha verification when keys are configured
            if ($hcaptchaSecretKey !== '' && $hcaptchaSiteKey !== '') {
                $captchaResponse = $request->request->get('h-captcha-response');
                if ($captchaResponse === null || trim((string) $captchaResponse) === '') {
                    $error = 'Please complete the security verification (captcha) before signing in.';
                } else {
                    $verified = $this->verifyHcaptcha($httpClient, $hcaptchaSecretKey, (string) $captchaResponse);
                    if (!$verified) {
                        $error = 'Security verification failed. Please try again.';
                    }
                }
            }

            if ($error === null && $email !== '' && $password !== null) {
                $user = $userRepository->findOneBy(['email_user' => $email]);
                if ($user !== null && $passwordHasher->isPasswordValid($user, $password)) {
                    if (!$user->getIsVerified()) {
                        $error = 'Your account is not yet verified by an administrator. You cannot log in until your account is verified.';
                    } else {
                        // Check 2FA from DB so we always use current state (same as profile / 2fa status)
                        $conn = $em->getConnection();
                        $twoFaRow = $conn->fetchAssociative(
                            'SELECT two_factor_enabled, totp_secret FROM user WHERE id_user = :id',
                            ['id' => $user->getIdUser()],
                            ['id' => \PDO::PARAM_INT]
                        );
                        $twoFactorEnabled = $twoFaRow ? (bool) ($twoFaRow['two_factor_enabled'] ?? false) : false;
                        $totpConfigured = $twoFaRow && isset($twoFaRow['totp_secret']) && $twoFaRow['totp_secret'] !== '' && $twoFaRow['totp_secret'] !== null;

                        if ($twoFactorEnabled) {
                            if ($totpConfigured) {
                                // Authenticator app: set session and either return JSON (AJAX) or render with popup
                                $session->set('2fa_user_id', $user->getIdUser());
                                $session->set('2fa_email', $user->getEmailUser());
                                $activityLogger->logAuthAction('LOGIN_CHALLENGE', 'SUCCESS', 'Password accepted; authenticator app verification required.', [
                                    'method' => 'password_totp',
                                    'email' => $email,
                                ], $user);
                                if ($isAjax) {
                                    return $this->json(['success' => true, 'requireTotp' => true]);
                                }
                                return $this->render('frontend/auth/sign-in.html.twig', [
                                    'error' => null,
                                    'last_email' => $lastEmail,
                                    'show_totp_popup' => true,
                                    'hcaptcha_site_key' => $hcaptchaSiteKey,
                                ]);
                            }
                            // Email OTP only: send code (stored in user.authCode), show OTP popup or redirect
                            $twoFactorService->sendCode($user);
                            $session->set('2fa_user_id', $user->getIdUser());
                            $session->set('2fa_email', $user->getEmailUser());
                            $activityLogger->logAuthAction('LOGIN_CHALLENGE', 'SUCCESS', 'Password accepted; email OTP verification required.', [
                                'method' => 'password_email_otp',
                                'email' => $email,
                            ], $user);
                            if ($isAjax) {
                                return $this->json(['success' => true, 'requireEmailOtp' => true]);
                            }
                            return $this->redirectToRoute('2fa_verify');
                        }

                        // No 2FA: proceed with normal login
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
                        $redirectUrl = $this->generateUrl('main_home');
                        if ($onboarding === null || !$onboarding->isCompleted()) {
                            $redirectUrl = $this->generateUrl('onboarding');
                        } else {
                            $adminRoles = ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'];
                            if (in_array($user->getRoleUser(), $adminRoles, true)) {
                                $redirectUrl = $this->generateUrl('auth_sign_in', ['destination' => 'choice']);
                            }
                        }
                        $activityLogger->logAuthAction('LOGIN', 'SUCCESS', 'User signed in with password.', [
                            'method' => 'password',
                            'destination' => $redirectUrl,
                            'email' => $email,
                        ], $user);
                        if ($isAjax) {
                            return $this->json(['success' => true, 'redirect' => $redirectUrl]);
                        }
                        if ($redirectUrl !== $this->generateUrl('main_home')) {
                            if ($redirectUrl === $this->generateUrl('auth_sign_in', ['destination' => 'choice'])) {
                                return $this->render('frontend/auth/sign-in.html.twig', [
                                    'error' => null,
                                    'last_email' => $lastEmail,
                                    'show_destination_choice' => true,
                                    'hcaptcha_site_key' => $hcaptchaSiteKey,
                                ]);
                            }
                            return $this->redirect($redirectUrl);
                        }
                        return $this->redirectToRoute('main_home');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } elseif ($error === null) {
                $error = 'Please enter your email and password.';
            }

            if ($error !== null) {
                $activityLogger->logAuthAction('LOGIN', 'FAILURE', $error, [
                    'method' => 'password',
                    'email' => $lastEmail,
                ], $user);
            }

            if ($isAjax) {
                return $this->json(['success' => false, 'error' => $error], 400);
            }
            // PRG: redirect to GET with error in session so browser doesn't resubmit POST on refresh
            $session->set('signin_error', $error);
            $session->set('signin_last_email', $lastEmail);
            return $this->redirectToRoute('auth_sign_in', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('frontend/auth/sign-in.html.twig', [
            'error' => $error,
            'last_email' => $lastEmail,
            'hcaptcha_site_key' => $hcaptchaSiteKey,
        ]);
    }

    private function verifyHcaptcha(HttpClientInterface $httpClient, string $secret, string $response): bool
    {
        if ($secret === '' || $response === '') {
            return false;
        }
        try {
            $result = $httpClient->request('POST', 'https://hcaptcha.com/siteverify', [
                'body' => [
                    'secret' => $secret,
                    'response' => $response,
                ],
            ]);
            $data = $result->toArray();
            return isset($data['success']) && $data['success'] === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    #[Route('/logout', name: 'auth_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('is_logged_in');
        $session->remove('user');
        return $this->redirectToRoute('main_home', ['logout' => 'success']);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function adminLogout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('is_logged_in');
        $session->remove('user');
        return $this->redirectToRoute('auth_sign_in');
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
        \App\Repository\Forum\CommentaireRepository $commentaireRepository,
        \App\Repository\Forum\ReactionRepository $reactionRepository,
        \App\Repository\Evenement\EvenementRepository $evenementRepository,
        \App\Repository\Residence\AppartementRepository $appartementRepository,
        \App\Repository\OAuth\OAuthRepository $oauthRepository,
        \App\Repository\Log\AppEventLogRepository $appEventLogRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        \App\Repository\UserStanding\UserStandingRepository $userStandingRepository,
        \App\Repository\UserRelationship\UserRelationshipRepository $userRelationshipRepository,
        HttpClientInterface $httpClient,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(param: 'mailer_oauth_user_id')]
        int $mailerOauthUserId = 0
    ): Response {
        if (!$pageStatusService->isPageOnline('profile')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'profile']);
        }

        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            $this->addFlash('danger', 'Please sign in to view your profile.');
            return $this->redirectToRoute('auth_sign_in');
        }
        $user = $userRepository->find($userId);
        if (!$user) {
            $session->remove('is_logged_in');
            $session->remove('user');
            $this->addFlash('danger', 'User not found.');
            return $this->redirectToRoute('auth_sign_in');
        }

        // Read 2FA state directly from DB so it stays correct after TOTP verify
        $conn = $em->getConnection();
        $twoFaRow = $conn->fetchAssociative(
            'SELECT two_factor_enabled, totp_secret FROM user WHERE id_user = :id',
            ['id' => $userId],
            ['id' => \PDO::PARAM_INT]
        );
        $twoFactorEnabled = $twoFaRow ? (bool) ($twoFaRow['two_factor_enabled'] ?? false) : false;
        $totpConfigured = $twoFaRow && isset($twoFaRow['totp_secret']) && $twoFaRow['totp_secret'] !== '' && $twoFaRow['totp_secret'] !== null;

        $gmailOauthConnected = $oauthRepository->findOneByUserId($userId) !== null;
        $isMailerOauthUser = $mailerOauthUserId > 0 && $userId === $mailerOauthUserId;
        $profileActivityLogs = $appEventLogRepository->findLatestByUser($userId, 14);
        $profileSecurityLogs = $appEventLogRepository->findSecurityByUser($userId, 10);
        $profileSecuritySummary = $appEventLogRepository->getUserSecuritySummary($userId);

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

        $userStanding = $userStandingRepository->findOneBy(['user' => $user]);
        if (!$userStanding) {
            $userStanding = new \App\Entity\UserStanding\UserStanding();
            $userStanding->setUser($user);
            $userStanding->setLevel(1);
            $userStanding->setPoints(0);
            $userStanding->setStandingLabel('NORMAL');
            $userStanding->setUpdatedAt(new \DateTime());
            $em->persist($userStanding);
            $em->flush();
        }

        $friendCount = $userRelationshipRepository->countFriends($user);
        $pendingFriendCount = $userRelationshipRepository->countPendingRequests($user);
        $sampleFriends = $userRelationshipRepository->findFriends($user, 6);
        $pendingRequests = $userRelationshipRepository->findPendingRequestsFor($user);

        // Build a map of userId -> avatar URL for friends
        $friendAvatarMap = [];
        foreach ($sampleFriends as $friend) {
            $fp = $profileRepository->findOneByUser($friend);
            $friendAvatarMap[$friend->getIdUser()] = $fp && $fp->getAvatar()
                ? ($fp->getAvatar())
                : null;
        }

        // Build a map of userId -> avatar URL for pending request senders
        $pendingAvatarMap = [];
        foreach ($pendingRequests as $rel) {
            $sender = $rel->getStatus() === 'PENDING_FIRST_SECOND' ? $rel->getUserFirst() : $rel->getUserSecond();
            if ($sender && !isset($pendingAvatarMap[$sender->getIdUser()])) {
                $sp = $profileRepository->findOneByUser($sender);
                $pendingAvatarMap[$sender->getIdUser()] = $sp && $sp->getAvatar() ? $sp->getAvatar() : null;
            }
        }

        $profileForm = $this->createForm(ProfileType::class, $profile);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $avatarSource = (string) $request->request->get('avatar_source', 'upload');
            $avatarPrompt = trim((string) $request->request->get('avatar_prompt', ''));

            try {
                if ($avatarSource === 'generate') {
                    $generatedAvatar = $this->generateProfileAvatarFromPrompt($httpClient, $avatarPrompt, $userId);
                    $profile->setAvatar($generatedAvatar);
                    $userData = $session->get('user', []);
                    $userData['avatar'] = $generatedAvatar;
                    $session->set('user', $userData);
                } else {
                    /** @var UploadedFile|null $avatarFile */
                    $avatarFile = $profileForm->get('avatarFile')->getData();
                    if ($avatarFile) {
                        $storedAvatar = $this->storeProfileAvatarFile($avatarFile, $userId);
                        $profile->setAvatar($storedAvatar);
                        $userData = $session->get('user', []);
                        $userData['avatar'] = $storedAvatar;
                        $session->set('user', $userData);
                    }
                }
            } catch (\Throwable $e) {
                $this->addFlash('danger', $e->getMessage());
            }

            $currentPassword = $profileForm->get('currentPassword')->getData();
            $newPassword = $profileForm->get('newPassword')->getData();

            if ($newPassword) {
                if (!$currentPassword) {
                    $this->addFlash('danger', 'You must provide your current password to change it.');
                } else {
                    if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                        $this->addFlash('danger', 'Current password is invalid.');
                    } else {
                        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                        $user->setPasswordUser($hashedPassword);
                        $this->addFlash('success', 'Password updated successfully.');
                    }
                }
            }

            $em->flush();

            if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return $this->json(['success' => true, 'message' => 'Profile updated successfully.']);
            }

            $this->addFlash('success', 'Profile updated.');
            return $this->redirectToRoute('frontend_profile');
        }

        if (($profileForm->isSubmitted() && !$profileForm->isValid()) && ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest')) {
            return $this->json(['success' => false, 'errors' => $this->getFormErrors($profileForm)], 400);
        }

        $onboardingFormView = null;
        if ($onboarding !== null) {
            $onboardingFormView = $this->createForm(OnboardingType::class, $onboarding, ['admin_edit' => false, 'profile_edit' => true])->createView();
        }

        $isAdmin = in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC']);
        if ($isAdmin) {
            $reclamations = $reclamationRepository->findBy([], ['created_at' => 'DESC'], 24);
        } else {
            $reclamations = $reclamationRepository->findBy(['user' => $user], ['created_at' => 'DESC'], 24);
        }

        $publicDir = $this->getParameter('kernel.project_dir') . '/public/reclamation_images/';
        foreach ($reclamations as $rec) {
            $updatedAt = $rec->getUpdatedAt() ? $rec->getUpdatedAt()->getTimestamp() : 0;
            $cacheKey = 'rec_images_' . $rec->getId() . '_' . $updatedAt;

            $rec->decodedImages = $this->cache->get($cacheKey, function (ItemInterface $item) use ($rec, $publicDir) {
                $item->expiresAfter(604800);
                $imgStr = $rec->getImagereclamation();
                if ($imgStr) {
                    $jsonDecoded = json_decode($imgStr, true);
                    if (is_array($jsonDecoded)) {
                        return array_values(array_filter($jsonDecoded, static fn ($path) => is_string($path) && $path !== ''));
                    }
                }
                $detectedFolders = [];
                if ($imgStr) {
                    preg_match_all('/([a-zA-Z0-9_\-\.]+_[0-9\-\_]{10,20})/', $imgStr, $matches);
                    if (!empty($matches[0])) {
                        $detectedFolders = array_merge($detectedFolders, $matches[0]);
                    }
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

            foreach ($rec->getReponses() as $reponse) {
                $respImgStr = $reponse->getImagereponse();
                $respDecodedImages = [];
                if ($respImgStr) {
                    $jsonDecoded = json_decode($respImgStr, true);
                    if (is_array($jsonDecoded) && !empty($jsonDecoded)) {
                        $respDecodedImages = $jsonDecoded;
                    } elseif (!str_contains($respImgStr, '.')) {
                    } else {
                        $respDecodedImages[] = $respImgStr;
                    }
                }
                $reponse->decodedImages = $respDecodedImages;
            }
        }

        if ($isAdmin) {
            $publications = $publicationRepository->findBy([], ['date_creation_pub' => 'DESC'], 24);
            $commentaires = $commentaireRepository->findBy([], ['created_at' => 'DESC'], 24);
            $reactions = $reactionRepository->findBy([], ['created_at' => 'DESC'], 48);
            $events = $evenementRepository->findBy([], ['date_event' => 'DESC'], 24);
            $appartements = $appartementRepository->findBy([], [], 24);
        } else {
            $publications = $publicationRepository->findBy(['user' => $user], ['date_creation_pub' => 'DESC'], 24);
            $commentaires = $commentaireRepository->findBy(['user' => $user], ['created_at' => 'DESC'], 24);
            $reactions = $reactionRepository->findBy(['user' => $user], ['created_at' => 'DESC'], 48);
            $events = $evenementRepository->findBy(['user' => $user], ['date_event' => 'DESC'], 24);
            $appartements = $appartementRepository->findBy(['user' => $user], [], 24);
        }

        $response = $this->render('frontend/profile/profile.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'profileForm' => $profileForm->createView(),
            'onboarding' => $onboarding,
            'onboardingForm' => $onboardingFormView,
            'reclamations' => $reclamations,
            'publications' => $publications,
            'commentaires' => $commentaires,
            'reactions' => $reactions,
            'events' => $events,
            'appartements' => $appartements,
            'isAdmin' => $isAdmin,
            'two_factor_enabled' => $twoFactorEnabled,
            'totp_configured' => $totpConfigured,
            'gmail_oauth_connected' => $gmailOauthConnected,
            'is_mailer_oauth_user' => $isMailerOauthUser,
            'userStanding' => $userStanding,
            'friendCount' => $friendCount,
            'pendingFriendCount' => $pendingFriendCount,
            'sampleFriends' => $sampleFriends,
            'pendingRequests' => $pendingRequests,
            'friendAvatarMap' => $friendAvatarMap,
            'pendingAvatarMap' => $pendingAvatarMap,
            'profileActivityLogs' => $profileActivityLogs,
            'profileSecurityLogs' => $profileSecurityLogs,
            'profileSecuritySummary' => $profileSecuritySummary,
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        return $response;
    }

    #[Route('/profile/onboarding-update', name: 'frontend_profile_onboarding_update', methods: ['POST'])]
    public function profileOnboardingUpdate(Request $request, UserRepository $userRepository, OnboardingRepository $onboardingRepository, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            $this->addFlash('danger', 'Please sign in to update onboarding.');
            return $this->redirectToRoute('auth_sign_in');
        }
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

            if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return $this->json(['success' => true, 'message' => 'Onboarding choices updated.']);
            }

            $this->addFlash('success', 'Onboarding choices updated.');
            return $this->redirectToRoute('frontend_profile');
        }

        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->json(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
        }

        $this->addFlash('danger', 'Invalid form.');
        return $this->redirectToRoute('frontend_profile');
    }

    #[Route('/profile/avatar-upload', name: 'frontend_profile_avatar_upload', methods: ['POST'])]
    public function profileAvatarUpload(Request $request, UserRepository $userRepository, ProfileRepository $profileRepository, EntityManagerInterface $em, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            $this->addFlash('danger', 'Please sign in to upload an avatar.');
            return $this->redirectToRoute('auth_sign_in');
        }
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

        $avatarSource = (string) $request->request->get('avatar_source', 'upload');
        $avatarPrompt = trim((string) $request->request->get('avatar_prompt', ''));

        try {
            if ($avatarSource === 'generate') {
                $avatarPath = $this->generateProfileAvatarFromPrompt($httpClient, $avatarPrompt, $userId);
            } else {
                /** @var UploadedFile|null $file */
                $file = $request->files->get('avatar_file');
                if (!$file || !$file->isValid()) {
                    throw new \RuntimeException('Please choose a valid image file.');
                }

                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!\in_array($file->getMimeType(), $allowed, true)) {
                    throw new \RuntimeException('Only JPEG, PNG, GIF and WebP images are allowed.');
                }

                $avatarPath = $this->storeProfileAvatarFile($file, $userId);
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('frontend_profile');
        }

        $profile->setAvatar($avatarPath);
        $em->flush();

        $userData = $session->get('user', []);
        $userData['avatar'] = $avatarPath;
        $session->set('user', $userData);

        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->json(['success' => true, 'message' => 'Avatar updated successfully.', 'avatar' => $userData['avatar']]);
        }

        $this->addFlash('success', 'Avatar updated.');
        return $this->redirectToRoute('frontend_profile');
    }

    private function storeProfileAvatarFile(UploadedFile $file, int $userId): string
    {
        $ext = $file->guessExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'jpg';
        $safeExt = \in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? strtolower($ext) : 'jpg';
        $filename = 'avatar_' . $userId . '_' . uniqid('', true) . '.' . $safeExt;
        $dir = $this->getParameter('kernel.project_dir') . '/public/profile_images';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            return $this->imageStorage->storeUploadedFile(
                $file,
                $dir,
                'profile_images',
                '/syndicati/profile_images',
                $filename
            );
        } catch (FileException $e) {
            throw new \RuntimeException('Could not save the image. Please try again.');
        }
    }

    private function generateProfileAvatarFromPrompt(HttpClientInterface $httpClient, string $prompt, int $userId): string
    {
        $prompt = trim(preg_replace('/\\s+/', ' ', $prompt) ?? '');
        if ($prompt === '') {
            throw new \RuntimeException('Please provide a prompt for the AI image.');
        }

        $prompt = mb_substr($prompt, 0, 180);
        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt) . '?width=512&height=512&nologo=true&enhance=true';

        try {
            $response = $httpClient->request('GET', $url, ['timeout' => 60]);
            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Pollinations AI could not generate the image right now.');
            }

            $headers = $response->getHeaders(false);
            $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));
            if (!str_starts_with($contentType, 'image/')) {
                throw new \RuntimeException('Pollinations returned an invalid image response.');
            }

            $extension = 'png';
            if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
                $extension = 'jpg';
            } elseif (str_contains($contentType, 'webp')) {
                $extension = 'webp';
            } elseif (str_contains($contentType, 'gif')) {
                $extension = 'gif';
            }

            $imageData = $response->getContent();
        } catch (\Throwable $e) {
            throw new \RuntimeException('AI image generation failed. Please try again.');
        }

        $filename = 'avatar_' . $userId . '_' . uniqid('', true) . '.' . $extension;
        $dir = $this->getParameter('kernel.project_dir') . '/public/profile_images';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = @file_put_contents($dir . '/' . $filename, $imageData);
        if ($saved === false) {
            throw new \RuntimeException('Could not save the generated image. Please try again.');
        }

        return $this->imageStorage->uploadLocalFile($dir . '/' . $filename, '/syndicati/profile_images', $filename)
            ?: 'profile_images/' . $filename;
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

    #[Route('/profile/search-residents', name: 'frontend_profile_search_residents', methods: ['POST'])]
    public function searchResidents(
        Request $request,
        UserRepository $userRepository,
        UserRelationshipRepository $userRelationshipRepository,
        ProfileRepository $profileRepository
    ): JsonResponse {
        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = trim((string) $request->request->get('query'));
        if (strlen($query) < 2) {
            return $this->json(['success' => true, 'residents' => []]);
        }

        $currentUser = $userRepository->find($userId);
        if (!$currentUser)
            return $this->json(['success' => false, 'message' => 'User not found'], 404);

        $results = $userRepository->searchByName($query, $userId, 10);
        $residents = [];

        foreach ($results as $resident) {
            $rel = $userRelationshipRepository->findRelationship($currentUser, $resident);
            if (!$rel) {
                $residentProfile = $profileRepository->findOneByUser($resident);
                $avatarUrl = null;
                if ($residentProfile && $residentProfile->getAvatar()) {
                    $av = $residentProfile->getAvatar();
                    $avatarUrl = str_starts_with($av, 'http') ? $av : '/' . ltrim($av, '/');
                }
                $residents[] = [
                    'id' => $resident->getIdUser(),
                    'name' => $resident->getFirstName() . ' ' . $resident->getLastName(),
                    'avatar' => $avatarUrl ?? 'https://ui-avatars.com/api/?name=' . urlencode($resident->getFirstName() . '+' . $resident->getLastName()) . '&background=random&color=fff&bold=true'
                ];
            }
        }

        return $this->json(['success' => true, 'residents' => $residents]);
    }

    #[Route('/profile/add-resident', name: 'frontend_profile_add_resident', methods: ['POST'])]
    public function addResident(
        Request $request,
        UserRepository $userRepository,
        UserRelationshipRepository $userRelationshipRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $targetId = (int) $request->request->get('targetId');
        if (!$targetId || $targetId === $userId) {
            return $this->json(['success' => false, 'message' => 'Invalid target'], 400);
        }

        $currentUser = $userRepository->find($userId);
        $targetUser = $userRepository->find($targetId);

        if (!$currentUser || !$targetUser) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $existing = $userRelationshipRepository->findRelationship($currentUser, $targetUser);
        if ($existing) {
            return $this->json(['success' => false, 'message' => 'Relationship already exists'], 400);
        }

        $relationship = new \App\Entity\UserRelationship\UserRelationship();
        $relationship->setUserFirst($currentUser);
        $relationship->setUserSecond($targetUser);
        $relationship->setStatus('PENDING_FIRST_SECOND');

        $em->persist($relationship);
        $em->flush();

        // Create notification for target user
        $this->notifService->notify(
            $targetUser,
            'FRIEND_REQUEST',
            'RELATIONSHIP',
            $relationship->getId(),
            'Nouvelle invitation',
            $currentUser->getFirstName() . ' souhaite devenir votre ami.'
        );

        // Feedback for current user (Push to island)
        $this->notifService->notify(
            $currentUser,
            'SUCCESS',
            'RELATIONSHIP',
            $relationship->getId(),
            'Invitation envoyée',
            'Votre demande a été transmise à ' . $targetUser->getFirstName() . '.'
        );

        $this->userStandingService->awardForAction($currentUser, 'FRIEND_REQUEST');

        return $this->json(['success' => true, 'message' => 'Request sent!']);
    }

    #[Route('/profile/accept-resident', name: 'frontend_profile_accept_resident', methods: ['POST'])]
    public function acceptResident(
        Request $request,
        UserRepository $userRepository,
        UserRelationshipRepository $userRelationshipRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        $relationshipId = (int) $request->request->get('relationshipId');
        $relationship = $userRelationshipRepository->find($relationshipId);
        if (!$relationship) {
            return $this->json(['success' => false, 'message' => 'Request not found'], 404);
        }
        $isRecipient = (
            ($relationship->getStatus() === 'PENDING_FIRST_SECOND' && $relationship->getUserSecond()?->getIdUser() === $userId) ||
            ($relationship->getStatus() === 'PENDING_SECOND_FIRST' && $relationship->getUserFirst()?->getIdUser() === $userId)
        );
        if (!$isRecipient) {
            return $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $currentUser = $userRepository->find($userId);
        $relationship->setStatus('FRIENDS');
        $em->flush();

        $sender = ($relationship->getUserFirst()->getIdUser() === $userId) ? $relationship->getUserSecond() : $relationship->getUserFirst();

        // Notify the person who sent the request
        $this->notifService->notify(
            $sender,
            'RELATIONSHIP_ACCEPTED',
            'RELATIONSHIP',
            $relationship->getId(),
            'Invitation acceptée',
            $currentUser->getFirstName() . ' a accepté votre invitation.'
        );

        // Confirmation for current user
        $this->notifService->notify(
            $currentUser,
            'SUCCESS',
            'RELATIONSHIP',
            $relationship->getId(),
            'Ami ajouté',
            'Vous êtes maintenant ami avec ' . $sender->getFirstName() . '.'
        );
        return $this->json(['success' => true, 'message' => 'Friend request accepted!']);
    }

    #[Route('/profile/decline-resident', name: 'frontend_profile_decline_resident', methods: ['POST'])]
    public function declineResident(
        Request $request,
        UserRepository $userRepository,
        UserRelationshipRepository $userRelationshipRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        $userId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $userId === null) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        $relationshipId = (int) $request->request->get('relationshipId');
        $relationship = $userRelationshipRepository->find($relationshipId);
        if (!$relationship) {
            return $this->json(['success' => false, 'message' => 'Request not found'], 404);
        }
        $isRecipient = (
            ($relationship->getStatus() === 'PENDING_FIRST_SECOND' && $relationship->getUserSecond()?->getIdUser() === $userId) ||
            ($relationship->getStatus() === 'PENDING_SECOND_FIRST' && $relationship->getUserFirst()?->getIdUser() === $userId)
        );
        if (!$isRecipient) {
            return $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $em->remove($relationship);
        $em->flush();
        return $this->json(['success' => true, 'message' => 'Request declined.']);
    }

    #[Route('/profile/friend-info/{id}', name: 'frontend_profile_friend_info', methods: ['GET'])]
    public function friendInfo(
        int $id,
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        \App\Repository\UserStanding\UserStandingRepository $userStandingRepository,
        UserRelationshipRepository $userRelationshipRepository
    ): JsonResponse {
        $session = $request->getSession();
        $myId = $this->getSessionUserId($session);
        if (!$session->get('is_logged_in') || $myId === null) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $me = $userRepository->find($myId);
        $friend = $userRepository->find($id);
        if (!$friend) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        // Only return info if they are actually friends or it's the current user
        if ($id !== $myId) {
            $rel = $userRelationshipRepository->findRelationship($me, $friend);
            if (!$rel || $rel->getStatus() !== 'FRIENDS') {
                return $this->json(['success' => false, 'message' => 'Not a friend'], 403);
            }
        }

        $fp = $profileRepository->findOneByUser($friend);
        $standing = $userStandingRepository->findOneBy(['user' => $friend]);

        $avatarUrl = null;
        if ($fp && $fp->getAvatar()) {
            $av = $fp->getAvatar();
            $avatarUrl = str_starts_with($av, 'http') ? $av : '/' . ltrim($av, '/');
        }

        return $this->json([
            'success' => true,
            'name' => $friend->getFirstName() . ' ' . $friend->getLastName(),
            'email' => $friend->getEmailUser(),
            'role' => $friend->getRoleUser(),
            'created' => $friend->getCreatedAt()?->format('d/m/Y'),
            'xp' => $standing ? $standing->getPoints() : 0,
            'level' => $standing ? $standing->getLevel() : 1,
            'avatar' => $avatarUrl,
        ]);
    }
}

