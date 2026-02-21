<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class MailerController extends AbstractController
{
    #[Route('/email', name: 'envoyer_email_residence')]
    public function envoyerEmail(Request $request, MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('syndcati@gmail.com')
            ->to($request->request->get('email_u'))
            ->subject('Demande location de votre appartement!')
            ->text(($request->request->get('message_u')));

        $mailer->send($email);  

        return $this->render('frontend/main-home.html.twig',
        [ 'email_envoyé'=> true,]);
        
    }
}