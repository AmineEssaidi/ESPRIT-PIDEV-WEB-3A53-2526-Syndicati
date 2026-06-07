<?php

namespace App\Controller\Auth;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\OAuth\GoogleOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class SocialAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator,
        private readonly string $fromEmail,
        private readonly string $fromName
    ) {
    }

    private function getRedirectUri(Request $request): string
    {
        return $this->urlGenerator->generate('auth_google_callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[Route('/auth/google/connect', name: 'auth_google_connect', methods: ['GET'])]
    public function googleConnect(Request $request): Response
    {
        $redirectUri = $this->getRedirectUri($request);
        $url = $this->googleOAuthService->getLoginAuthorizationUrl(null, $redirectUri);
        return $this->redirect($url);
    }

    #[Route('/auth/google/callback', name: 'auth_google_callback', methods: ['GET'])]
    public function googleCallback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('danger', 'Google access denied: ' . $error);
            return $this->redirectToRoute('auth_sign_in');
        }

        if (!$code) {
            $this->addFlash('danger', 'No authorization code received.');
            return $this->redirectToRoute('auth_sign_in');
        }

        try {
            $redirectUri = $this->getRedirectUri($request);
            $userInfo = $this->googleOAuthService->getUserInfo($code, $redirectUri);

            $email = $userInfo['email'];
            $googleId = $userInfo['id'];

            // 1. Match by google_id
            $user = $this->userRepository->findOneBy(['google_id' => $googleId]);

            if ($user) {
                $this->addFlash('success', 'Welcome back! Synchronized via Google Identity.');
            } else {
                // 2. Match by email (linking)
                $user = $this->userRepository->findOneBy(['email_user' => $email]);
                if ($user) {
                    $user->setGoogleId($googleId);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Successfully linked your existing Syndicati account to Google.');
                    $this->sendLinkNotificationEmail($user);
                } else {
                    // 3. Create new user
                    $user = new User();
                    $user->setEmailUser($email);
                    $user->setGoogleId($googleId);
                    // Fixed: Use correct keys from GoogleOAuthService::getUserInfo
                    $user->setFirstName($userInfo['firstName'] ?? 'User');
                    $user->setLastName($userInfo['lastName'] ?? 'Google');
                    $user->setRoleUser('RESIDENT');
                    $user->setIsVerified(true);

                    // Manual timestamps to be safe
                    $now = new \DateTime();
                    $user->setCreatedAt($now);
                    $user->setUpdatedAt($now);

                    $plainPassword = $this->generateRandomPassword();
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPasswordUser($hashedPassword);

                    $this->entityManager->persist($user);

                    // Create Profile as well
                    $profile = new \App\Entity\Profile\Profile();
                    $profile->setUser($user);
                    if (isset($userInfo['picture'])) {
                        $profile->setAvatar($userInfo['picture']);
                    }
                    $this->entityManager->persist($profile);

                    $this->entityManager->flush();

                    $this->addFlash('success', 'Welcome! Your account has been provisioned via Google Identity.');
                    $this->sendWelcomeEmail($user, $plainPassword);
                }
            }

            // 4. Session provisioning
            $session = $request->getSession();
            $session->set('is_logged_in', true);
            $session->set('user', [
                'id' => $user->getIdUser(),
                'email' => $user->getEmailUser(),
                'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                'role' => $user->getRoleUser(),
            ]);

            return in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'], true)
                ? $this->redirectToRoute('auth_sign_in', ['destination' => 'choice'])
                : $this->redirectToRoute('main_home');

        } catch (\Throwable) {
            $this->addFlash('danger', 'Google authentication failed. Please try again.');
            return $this->redirectToRoute('auth_sign_in');
        }
    }

    private function sendLinkNotificationEmail(User $user): void
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($user->getEmailUser())
            ->subject('Syndicati Identity Protocol: Google Account Linked')
            ->html($this->twig->render('emails/google_linked_premium.html.twig', [
                'user' => $user
            ]));

        $this->mailer->send($email);
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

    private function sendWelcomeEmail(User $user, string $password): void
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($user->getEmailUser())
            ->subject('Syndicati Identity Protocol: Account Synchronized')
            ->html($this->twig->render('emails/google_welcome_premium.html.twig', [
                'user' => $user,
                'password' => $password
            ]));

        $this->mailer->send($email);
    }
}
