<?php

namespace App\Controller\Test;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class MailingTestController extends AbstractController
{
    #[Route('/test-mail', name: 'app_test_mail')]
    public function testMail(MailerInterface $mailer): Response
    {
        $adminEmail = $this->getParameter('mailer_from_email');

        $email = (new Email())
            ->from($adminEmail)
            ->to($adminEmail)
            ->subject('Test Mailing - Syndicati')
            ->text('Ceci est un email de test pour vérifier votre configuration SMTP sur Horizon.')
            ->html('<h1 style="color: #6c5ce7;">Horizon Mailing Test</h1><p>Si vous recevez cet email, votre configuration Gmail/SMTP est <strong>correcte</strong> !</p>');

        try {
            $mailer->send($email);
            return new Response('
                <div style="font-family: sans-serif; padding: 2rem; border-radius: 20px; border: 1px solid #d4edda; background: #f8f9fa; max-width: 600px; margin: 4rem auto; text-align: center;">
                    <h1 style="color: #28a745;">Succès !</h1>
                    <p>L\'email de test a été envoyé avec succès à <strong>' . $adminEmail . '</strong>.</p>
                    <p>Vérifiez votre boîte mail (et vos spams).</p>
                    <a href="/" style="display: inline-block; padding: 0.8rem 1.5rem; background: #6c5ce7; color: #fff; text-decoration: none; border-radius: 10px; margin-top: 1rem;">Retour à l\'accueil</a>
                </div>
            ');
        } catch (\Exception $e) {
            return new Response('
                <div style="font-family: sans-serif; padding: 2rem; border-radius: 20px; border: 1px solid #f8d7da; background: #fff5f5; max-width: 600px; margin: 4rem auto; text-align: center;">
                    <h1 style="color: #dc3545;">Erreur d\'envoi</h1>
                    <p>L\'envoi a échoué. Voici le détail de l\'erreur :</p>
                    <pre style="text-align: left; background: #eee; padding: 1rem; border-radius: 10px; overflow-x: auto;">' . $e->getMessage() . '</pre>
                    <p>Vérifiez votre fichier <strong>.env</strong> et assurez-vous d\'utiliser un <strong>mot de passe d\'application</strong> Gmail.</p>
                    <a href="/" style="display: inline-block; padding: 0.8rem 1.5rem; background: #6c5ce7; color: #fff; text-decoration: none; border-radius: 10px; margin-top: 1rem;">Retour à l\'accueil</a>
                </div>
            ');
        }
    }

    #[Route('/test-sms', name: 'app_test_sms')]
    public function testSms(NotificationService $notification): Response
    {
        $user = $this->getUser();
        if (!$user || !$user->getTelephoneUser()) {
            return new Response('Erreur: Vous devez être connecté et avoir un numéro de téléphone configuré.');
        }

        $success = $notification->sendParticipationSms(new \App\Entity\Evenement\Participation()); // Dummy object for text only
        
        return new Response($success ? 'SMS envoyé !' : 'Erreur SMS (vérifiez Twilio).');
    }
}
