<?php
namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use Twig\Environment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\OAuth\GmailOAuthMailer;

class EvenementNotificationService
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
        GmailOAuthMailer $gmailOAuthMailer,
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

    public function notifyParticipationConfirmation(Participation $participation): void
    {
        $user = $participation->getUser();
        if (!$user || !$user->getEmailUser()) {
            return;
        }

        $evenement = $participation->getEvenement();

        $message = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($user->getEmailUser())
            ->subject('Spot Secured: ' . $evenement->getTitreEvent())
            ->html($this->twig->render('emails/event_participation_confirmation.html.twig', [
                'participation' => $participation,
                'evenement' => $evenement,
                'user' => $user
            ]));

        $this->sendEmail($message);
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
