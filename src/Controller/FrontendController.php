<?php

namespace App\Controller;

use App\Entity\Profile\Profile;
use App\Form\Profile\ProfileType;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Service\PageStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FrontendController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    #[Route('/', name: 'main_home')]
    public function mainHome(PageStatusService $pageStatusService, Request $request): Response
    {
        // Check if main home is offline
        if (!$pageStatusService->isPageOnline('main_home')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'main_home']); // Redirection remains, no user-facing string
        }
        
        $response = $this->render('frontend/home/main-home.html.twig');
        
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
        EntityManagerInterface $em
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

        $profileForm = $this->createForm(ProfileType::class, $profile);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profile updated.');
            return $this->redirectToRoute('frontend_profile');
        }

        $response = $this->render('frontend/profile/profile.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'profileForm' => $profileForm->createView(),
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        return $response;
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
    public function signIn(Request $request, UserRepository $userRepository, ProfileRepository $profileRepository, UserPasswordHasherInterface $passwordHasher): Response
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
                        $session->set('is_logged_in', true);
                        $session->set('user', [
                            'id' => $user->getIdUser(),
                            'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
                            'email' => $user->getEmailUser(),
                            'role' => $user->getRoleUser(),
                            'avatar' => $avatar,
                        ]);
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
        
        return $this->redirectToRoute('main_home');
    }
}
