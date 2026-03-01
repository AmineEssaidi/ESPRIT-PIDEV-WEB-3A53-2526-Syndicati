<?php

namespace App\Controller\TwoFactor;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\Onboarding\OnboardingRepository;
use App\Service\TwoFactor\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class TwoFactorController extends AbstractController
{
    #[Route('/2fa/verify', name: '2fa_verify', methods: ['GET', 'POST'])]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        OnboardingRepository $onboardingRepository,
        TwoFactorService $twoFactorService
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        $email = $session->get('2fa_email');

        // Redirect if no pending 2FA session
        if (!$userId || !$email) {
            return $this->redirectToRoute('auth_sign_in');
        }

        $user = $userRepository->find($userId);
        if (!$user || $user->getEmailUser() !== $email) {
            $session->remove('2fa_user_id');
            $session->remove('2fa_email');
            return $this->redirectToRoute('auth_sign_in');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code', ''));

            if ($code === '') {
                $error = 'Please enter the verification code.';
            } elseif ($twoFactorService->verifyCode($user, $code)) {
                // 2FA verified: complete login
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
                $session->remove('2fa_user_id');
                $session->remove('2fa_email');

                $onboarding = $onboardingRepository->findOneByUser($user);
                if ($onboarding === null || !$onboarding->isCompleted()) {
                    return $this->redirectToRoute('onboarding');
                }
                $adminRoles = ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'];
                if (in_array($user->getRoleUser(), $adminRoles, true)) {
                    return $this->render('frontend/auth/sign-in.html.twig', [
                        'error' => null,
                        'last_email' => $email,
                        'show_destination_choice' => true,
                    ]);
                }
                return $this->redirectToRoute('main_home');
            } else {
                $error = 'Invalid or expired verification code. Please try again.';
            }
        }

        return $this->render('security/two_factor_login.html.twig', [
            'error' => $error,
            'email' => $email,
        ]);
    }

    #[Route('/2fa/resend', name: '2fa_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        UserRepository $userRepository,
        TwoFactorService $twoFactorService
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        $email = $session->get('2fa_email');

        if (!$userId || !$email) {
            return $this->redirectToRoute('auth_sign_in');
        }

        $user = $userRepository->find($userId);
        if ($user && $user->getEmailUser() === $email) {
            $twoFactorService->sendCode($user);
            $this->addFlash('success', 'A new verification code has been sent to your email.');
        }

        return $this->redirectToRoute('2fa_verify');
    }

    #[Route('/2fa/toggle', name: '2fa_toggle', methods: ['POST'])]
    public function toggle(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        if (!$session->get('is_logged_in')) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $user = $userRepository->find($userData['id']);
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $enabled = isset($data['enabled']) && $data['enabled'] === true;

        $user->setTwoFactorEnabled($enabled);
        if (!$enabled) {
            $user->setTotpSecret(null);
        }
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => $enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled'
        ]);
    }

    #[Route('/2fa/send-test-code', name: '2fa_send_test_code', methods: ['POST'])]
    public function sendTestCode(
        Request $request,
        UserRepository $userRepository,
        TwoFactorService $twoFactorService
    ): JsonResponse {
        $session = $request->getSession();
        if (!$session->get('is_logged_in')) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $user = $userRepository->find($userData['id']);
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if (!$user->isTwoFactorEnabled()) {
            return $this->json(['success' => false, 'message' => '2FA is not enabled'], 400);
        }

        try {
            $twoFactorService->sendCode($user);
            return $this->json(['success' => true, 'message' => 'Test code sent to your email']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Failed to send code: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/2fa/generate-totp', name: '2fa_generate_totp', methods: ['POST'])]
    public function generateTotp(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        if (!$session->get('is_logged_in')) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $user = $userRepository->find($userData['id']);
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        try {
            $secret = $this->generateTotpSecret();
            $user->setTotpSecret($secret);
            $em->flush();

            $issuer = 'Horizon';
            $accountName = $user->getEmailUser();
            $qrCodeUrl = sprintf(
                'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                rawurlencode($issuer),
                rawurlencode($accountName),
                $secret,
                rawurlencode($issuer)
            );

            return $this->json([
                'success' => true,
                'qrCode' => $qrCodeUrl,
                'secret' => $secret
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to generate QR code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a TOTP-compatible secret (Base32 encoded, 32 random bytes).
     */
    private function generateTotpSecret(): string
    {
        if (!class_exists(\ParagonIE\ConstantTime\Base32::class)) {
            throw new \RuntimeException('TOTP support requires paragonie/constant_time_encoding. Run: composer require paragonie/constant_time_encoding');
        }
        return \ParagonIE\ConstantTime\Base32::encodeUpperUnpadded(random_bytes(32));
    }

    #[Route('/2fa/verify-totp', name: '2fa_verify_totp', methods: ['POST'])]
    public function verifyTotp(
        Request $request,
        UserRepository $userRepository,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        if (!$session->get('is_logged_in')) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $user = $userRepository->find($userData['id']);
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $code = isset($data['code']) ? trim((string) $data['code']) : '';
        $secretFromRequest = isset($data['secret']) ? trim((string) $data['secret']) : null;

        if ($code === '' || strlen($code) !== 6) {
            return $this->json(['success' => false, 'message' => 'Invalid code format'], 400);
        }

        $storedSecret = $user->getTotpSecret();

        // Use stored secret, or (if none) the secret sent from the modal (from the generate step)
        if ($storedSecret !== null && $storedSecret !== '') {
            $secretToUse = $storedSecret;
        } elseif ($secretFromRequest !== null && $secretFromRequest !== '') {
            $secretToUse = $secretFromRequest;
        } else {
            return $this->json([
                'success' => false,
                'message' => 'TOTP secret not set. Use the "Setup Authenticator App" button first—the QR code and manual entry code are in that modal. Then enter the 6-digit code from your app here.'
            ], 400);
        }

        // Verify code (using bundle's authenticator if user has secret set, else OTPHP directly)
        $codeValid = false;
        if ($storedSecret !== null && $storedSecret !== '') {
            $codeValid = $totpAuthenticator->checkCode($user, $code);
        } else {
            if (class_exists(\OTPHP\TOTP::class)) {
                $totp = \OTPHP\TOTP::createFromSecret($secretToUse);
                $totp->setPeriod(30);
                $totp->setDigits(6);
                $codeValid = $totp->verify($code, null, 1);
            }
        }

        if ($codeValid) {
            $userId = $user->getIdUser();
            // Native SQL so we're sure we write to the exact columns
            $conn = $em->getConnection();
            $conn->executeStatement(
                'UPDATE user SET two_factor_enabled = 1, totp_secret = :secret WHERE id_user = :id',
                ['secret' => $secretToUse, 'id' => $userId],
                ['id' => \PDO::PARAM_INT]
            );
            return $this->json(['success' => true, 'message' => 'TOTP verified successfully']);
        }

        return $this->json(['success' => false, 'message' => 'Invalid code'], 400);
    }

    #[Route('/2fa/status', name: '2fa_status', methods: ['GET'])]
    public function status(Request $request, UserRepository $userRepository): JsonResponse
    {
        $session = $request->getSession();
        if (!$session->get('is_logged_in')) {
            return $this->json(['success' => false], 401);
        }

        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return $this->json(['success' => false], 401);
        }

        $userId = (int) $userData['id'];
        $conn = $userRepository->getEntityManager()->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT two_factor_enabled, totp_secret FROM user WHERE id_user = :id',
            ['id' => $userId],
            ['id' => \PDO::PARAM_INT]
        );
        if (!$row) {
            return $this->json(['success' => false], 404);
        }

        return $this->json([
            'success' => true,
            'twoFactorEnabled' => (bool) ($row['two_factor_enabled'] ?? false),
            'totpConfigured' => isset($row['totp_secret']) && $row['totp_secret'] !== '' && $row['totp_secret'] !== null,
        ]);
    }

    #[Route('/2fa/remove-totp', name: '2fa_remove_totp', methods: ['POST'])]
    public function removeTotp(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        if (!$session->get('is_logged_in')) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $user = $userRepository->find($userData['id']);
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $user->setTotpSecret(null);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'TOTP removed successfully']);
    }

    #[Route('/2fa/check-channels', name: '2fa_check_channels', methods: ['POST'])]
    public function checkChannels(
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository
    ): JsonResponse {
        $content = $request->getContent();
        $data = is_string($content) && $content !== '' ? json_decode($content, true) : [];
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        if ($email === '') {
            return $this->json(['success' => false, 'message' => 'Email is required'], 400);
        }

        $user = $userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            // Standard security: don't reveal existence
            return $this->json(['success' => true, 'channels' => ['email']]);
        }

        $channels = ['email'];
        $rawPhone = $user->getPhone();
        $hasPhone = !empty($rawPhone) && strlen(trim($rawPhone)) > 0;

        if ($hasPhone) {
            $channels[] = 'sms';
        }

        return $this->json([
            'success' => true,
            'channels' => $channels,
            'phone' => $hasPhone ? substr($rawPhone, 0, 4) . '****' . substr($rawPhone, -2) : null,
        ]);
    }

    #[Route('/2fa/request-otp', name: '2fa_request_otp', methods: ['POST'])]
    public function requestOtp(
        Request $request,
        UserRepository $userRepository,
        TwoFactorService $twoFactorService
    ): JsonResponse {
        // Accept email from JSON body or form data (sign-in page sends JSON with the email from the form)
        $content = $request->getContent();
        $data = is_string($content) && $content !== '' ? json_decode($content, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $email = isset($data['email']) ? trim((string) $data['email']) : trim((string) $request->request->get('email', ''));
        $channel = isset($data['channel']) ? trim((string) $data['channel']) : 'email';

        if ($email === '') {
            return $this->json(['success' => false, 'message' => 'Email is required. Enter your email in the sign-in box and try again.'], 400);
        }

        // Case-insensitive lookup so "User@Mail.com" matches "user@mail.com"
        $user = $userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            // Don't reveal if user exists for security
            return $this->json(['success' => true, 'message' => 'If an account exists with this email, a code has been sent']);
        }

        try {
            $actualChannel = $channel === 'sms' ? 'sms' : 'email';
            $twoFactorService->sendCode($user, $actualChannel);
            $target = $actualChannel === 'sms' ? 'phone' : 'email';
            return $this->json(['success' => true, 'message' => 'OTP code sent to your ' . $target, 'channel' => $actualChannel]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => 'Failed to send code: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/2fa/verify-otp-login', name: '2fa_verify_otp_login', methods: ['POST'])]
    public function verifyOtpLogin(
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        OnboardingRepository $onboardingRepository,
        TwoFactorService $twoFactorService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $code = isset($data['code']) ? trim((string) $data['code']) : '';

        if ($email === '' || $code === '') {
            return $this->json(['success' => false, 'message' => 'Email and code are required'], 400);
        }

        $user = $userRepository->findOneByEmail($email);
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if (!$twoFactorService->verifyCode($user, $code)) {
            return $this->json(['success' => false, 'message' => 'Invalid or expired code'], 401);
        }

        // Code verified: complete login
        $session = $request->getSession();
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
        $redirectUrl = '/';
        if ($onboarding === null || !$onboarding->isCompleted()) {
            $redirectUrl = $this->generateUrl('onboarding');
        } else {
            $adminRoles = ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'];
            if (in_array($user->getRoleUser(), $adminRoles, true)) {
                $redirectUrl = $this->generateUrl('auth_sign_in') . '?destination=choice';
            } else {
                $redirectUrl = $this->generateUrl('main_home');
            }
        }

        return $this->json(['success' => true, 'redirect' => $redirectUrl]);
    }

    /**
     * Verify TOTP code during sign-in (user has 2FA with authenticator app).
     * Session must contain 2fa_user_id and 2fa_email from the initial sign-in POST.
     */
    #[Route('/2fa/verify-totp-login', name: '2fa_verify_totp_login', methods: ['POST'])]
    public function verifyTotpLogin(
        Request $request,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        OnboardingRepository $onboardingRepository,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        $email = $session->get('2fa_email');

        if (!$userId || !$email) {
            return $this->json(['success' => false, 'message' => 'Session expired. Please sign in again.'], 400);
        }

        $user = $userRepository->find($userId);
        if (!$user instanceof User || $user->getEmailUser() !== $email) {
            $session->remove('2fa_user_id');
            $session->remove('2fa_email');
            return $this->json(['success' => false, 'message' => 'Invalid session. Please sign in again.'], 400);
        }

        // Read TOTP secret from DB so we don't rely on entity cache
        $conn = $em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT totp_secret FROM user WHERE id_user = :id',
            ['id' => $userId],
            ['id' => \PDO::PARAM_INT]
        );
        $storedSecret = $row && isset($row['totp_secret']) && $row['totp_secret'] !== '' && $row['totp_secret'] !== null
            ? (string) $row['totp_secret']
            : null;
        if ($storedSecret === null) {
            return $this->json(['success' => false, 'message' => 'Authenticator app is not configured for this account.'], 400);
        }
        // Ensure entity has secret for TotpAuthenticatorInterface::checkCode()
        $user->setTotpSecret($storedSecret);

        $data = json_decode($request->getContent(), true);
        $code = isset($data['code']) ? trim((string) $data['code']) : '';

        if ($code === '' || strlen($code) !== 6) {
            return $this->json(['success' => false, 'message' => 'Please enter the 6-digit code from your authenticator app.'], 400);
        }

        if (!$totpAuthenticator->checkCode($user, $code)) {
            return $this->json(['success' => false, 'message' => 'Invalid code. Please try again.'], 401);
        }

        // TOTP valid: complete login
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
        $session->remove('2fa_user_id');
        $session->remove('2fa_email');

        $onboarding = $onboardingRepository->findOneByUser($user);
        $redirectUrl = $this->generateUrl('main_home');
        if ($onboarding === null || !$onboarding->isCompleted()) {
            $redirectUrl = $this->generateUrl('onboarding');
        } else {
            $adminRoles = ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'];
            if (in_array($user->getRoleUser(), $adminRoles, true)) {
                $redirectUrl = $this->generateUrl('auth_sign_in') . '?destination=choice';
            }
        }

        return $this->json(['success' => true, 'redirect' => $redirectUrl]);
    }

    /**
     * Cancel pending 2FA login (e.g. user closes TOTP popup).
     * AJAX: clear session and return JSON (no redirect). Non-AJAX: redirect to sign-in.
     */
    #[Route('/2fa/cancel-login', name: '2fa_cancel_login', methods: ['GET', 'POST'])]
    public function cancelLogin(Request $request): Response|JsonResponse
    {
        $request->getSession()->remove('2fa_user_id');
        $request->getSession()->remove('2fa_email');
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }
        return $this->redirectToRoute('auth_sign_in');
    }
}
