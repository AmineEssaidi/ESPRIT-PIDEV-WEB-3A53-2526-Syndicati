<?php
namespace App\Service\Residence;

use App\Entity\Residence\Appartement;
use App\Entity\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ResidenceNotificationService
{
    private $mailer;
    private $router;
    private $twig;
    private $gmailOAuthMailer;
    private $fromEmail;
    private $fromName;
    private $mailerDsn;

    public function __construct(
        MailerInterface $mailer,
        UrlGeneratorInterface $router,
        Environment $twig,
        \App\Service\OAuth\GmailOAuthMailer $gmailOAuthMailer,
        string $fromEmail,
        string $fromName,
        string $mailerDsn
    ) {
        $this->mailer = $mailer;
        $this->router = $router;
        $this->twig = $twig;
        $this->gmailOAuthMailer = $gmailOAuthMailer;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->mailerDsn = $mailerDsn;
    }

    public function notifyApartmentContact(Appartement $appartement, array $contactData): void
    {
        $owner = $appartement->getUser();
        if (!$owner || !$owner->getEmailUser()) {
            return;
        }

        $senderEmail = $contactData['email_u'];
        $senderName = $contactData['nom_u'];
        $messageContent = $contactData['message_u'] ?? '';

        // Prepare context for the template
        $context = [
            'appartement' => $appartement,
            'owner' => $owner,
            'senderName' => $senderName,
            'senderEmail' => $senderEmail,
            'messageContent' => $messageContent,
            'residence' => $appartement->getResidence()
        ];

        // 1. Send to Owner
        $ownerEmail = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($owner->getEmailUser())
            ->subject('Nouveau contact pour votre appartement: ' . $appartement->getTypeA())
            ->html($this->twig->render('emails/apartment_contact.html.twig', array_merge($context, ['isOwner' => true])));

        $this->sendEmail($ownerEmail);

        // 2. Send same copy to Sender (Confirmation)
        $senderMirrorEmail = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($senderEmail)
            ->subject('Copie de votre demande pour l\'appartement: ' . $appartement->getTypeA())
            ->html($this->twig->render('emails/apartment_contact.html.twig', array_merge($context, ['isOwner' => false])));

        $this->sendEmail($senderMirrorEmail);
    }

    private function sendEmail(\Symfony\Component\Mime\Email $email): void
    {
        $smtpConfigured = $this->mailerDsn !== '' && !str_starts_with($this->mailerDsn, 'null://');

        try {
            if ($smtpConfigured) {
                $this->mailer->send($email);
            } elseif ($this->gmailOAuthMailer->isAvailable()) {
                $this->gmailOAuthMailer->send($email);
            }
        } catch (\Exception $e) {
            // Fail silently or log error
        }
    }
}
