<?php
namespace App\Controller\User;

use App\Entity\Profile\Profile;
use App\Entity\User\User;
use App\Form\User\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/signup", name="user_signup")
     */
    public function signup(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'signup' => true,
            'validation_groups' => ['Default', 'registration']
        ]);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

            if ($form->isValid()) {
                // Check if email already exists
                $existingUser = $em->getRepository(User::class)->findOneBy(['email_user' => $user->getEmailUser()]);
                if ($existingUser) {
                    if ($isAjax) {
                        return $this->json(['success' => false, 'message' => 'This email is already registered.'], 400);
                    }
                    $form->get('email_user')->addError(new \Symfony\Component\Form\FormError('This email is already registered.'));
                    $this->addFlash('danger', 'This email is already registered.');
                } else {
                    // Hash password
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

                    // Create profile for the new user
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
            } else {
                // Collect all errors recursively
                $errorMessages = [];
                $formErrors = $form->getErrors(true);
                foreach ($formErrors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                if ($isAjax) {
                    return $this->json(['success' => false, 'errors' => $errorMessages], 400);
                }

                $joinedErrors = implode('|', $errorMessages);
                $this->addFlash('error_popup', $joinedErrors);
                $this->addFlash('danger', 'Please correct the errors in the form.');
            }
        }

        return $this->render('frontend/signup.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/signup/success", name="user_signup_success")
     */
    public function signupSuccess(): Response
    {
        return $this->render('frontend/signup_success.html.twig');
    }
}
