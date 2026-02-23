<?php

namespace App\Controller\Auth;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\TwoFactor\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[Route('/auth/forgot-password')]
class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TwoFactorService $twoFactorService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig
    ) {
    }

    #[Route('/request', name: 'auth_forgot_password_request', methods: ['POST'])]
    public function requestReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return $this->json(['success' => false, 'message' => 'Please enter your email address.'], 400);
        }

        $user = $this->userRepository->findOneBy(['email_user' => $email]);
        if (!$user) {
            // Security: Don't reveal account existence, but return a vague success
            return $this->json(['success' => true, 'message' => 'If this email is registered, you will receive a 2FA code shortly.']);
        }

        try {
            // Re-use TwoFactorService to send the OTP (which uses our premium template now)
            $this->twoFactorService->sendCode($user);
            return $this->json(['success' => true, 'message' => 'A 6-digit verification code has been sent to your email.']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Failed to send security code. Please try again.'], 500);
        }
    }

    #[Route('/verify', name: 'auth_forgot_password_verify', methods: ['POST'])]
    public function verifyReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $code = $data['code'] ?? '';

        if (empty($email) || empty($code)) {
            return $this->json(['success' => false, 'message' => 'Email and code are required.'], 400);
        }

        $user = $this->userRepository->findOneBy(['email_user' => $email]);
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Invalid session.'], 400);
        }

        if (!$this->twoFactorService->verifyCode($user, $code)) {
            return $this->json(['success' => false, 'message' => 'Invalid or expired code.'], 400);
        }

        try {
            // Generate a secure random password
            $newPassword = $this->generateRandomPassword();

            // Hash and update
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPasswordUser($hashedPassword);

            // Clear the 2FA code after successful verification
            $user->setAuthCode(null);
            $user->setAuthCodeExpiresAt(null);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Send Final Premium Email with the new password
            $this->sendPasswordEmail($user, $newPassword);

            return $this->json([
                'success' => true,
                'message' => 'Success! Your password has been reset. Please check your email for your new credentials.'
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'An error occurred while resetting your password.'], 500);
        }
    }

    private function generateRandomPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $pass = '';
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }

    private function sendPasswordEmail(User $user, string $newPassword): void
    {
        $email = (new Email())
            ->from('noreply@horizon.local')
            ->to($user->getEmailUser())
            ->subject('Horizon Protocol: Your New Credentials')
            ->html($this->twig->render('emails/password_reset_premium.html.twig', [
                'user' => $user,
                'new_password' => $newPassword
            ]));

        $this->mailer->send($email);
    }
}
