<?php
namespace App\Controller\User;

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
        $form = $this->createForm(UserType::class, $user, ['signup' => true]);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            error_log('Form IS submitted');
            if ($form->isValid()) {
                error_log('Form IS valid');
                // Check if email already exists
                $existingUser = $em->getRepository(User::class)->findOneBy(['email_user' => $user->getEmailUser()]);
                if ($existingUser) {
                    error_log('User already exists: ' . $user->getEmailUser());
                    $form->get('email_user')->addError(new \Symfony\Component\Form\FormError('This email is already registered.'));
                    $this->addFlash('danger', 'This email is already registered.');
                } else {
                    error_log('Creating new user: ' . $user->getEmailUser());
                    // Hash password
                    $plainPassword = $form->get('password_user')->getData();
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPasswordUser($hashedPassword);
                    $user->setRoleUser('RESIDENT');
                    $user->setIsVerified(false);
                    $now = new \DateTime();
                    $user->setCreatedAt($now);
                    $user->setUpdatedAt($now);

                    $em->persist($user);
                    $em->flush();

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
                $joinedErrors = implode('|', $errorMessages);
                $this->addFlash('error_popup', $joinedErrors);
                $this->addFlash('danger', 'Please correct the errors in the form.');
            }
        } else {
            error_log('Form is NOT submitted');
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
