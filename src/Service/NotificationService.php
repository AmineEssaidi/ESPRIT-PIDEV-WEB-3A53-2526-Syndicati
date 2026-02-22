<?php

namespace App\Service;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

class NotificationService
{
    private MailerInterface $mailer;
    private TexterInterface $texter;
    private string $fromEmail;
    private string $fromName;

    public function __construct(MailerInterface $mailer, TexterInterface $texter, string $fromEmail, string $fromName)
    {
        $this->mailer = $mailer;
        $this->texter = $texter;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function sendEventCreatedNotification(Evenement $evenement): bool
    {
        $user = $evenement->getUser();
        if (!$user || !$user->getEmailUser()) {
            return false;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($user->getEmailUser())
                ->addBcc(new Address($this->fromEmail, $this->fromName))
                ->subject('Confirmation de création d\'événement : ' . $evenement->getTitreEvent())
                ->htmlTemplate('emails/event_created.html.twig')
                ->context([
                    'evenement' => $evenement,
                    'user' => $user,
                ]);

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendParticipationConfirmation(Participation $participation): bool
    {
        $user = $participation->getUser();
        $evenement = $participation->getEvenement();

        if (!$user || !$user->getEmailUser()) {
            return false;
        }

        try {
            // 1. Send Email
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($user->getEmailUser())
                ->addBcc(new Address($this->fromEmail, $this->fromName))
                ->subject('Confirmation de participation : ' . $evenement->getTitreEvent())
                ->htmlTemplate('emails/participation_confirmed.html.twig')
                ->context([
                    'participation' => $participation,
                    'evenement' => $evenement,
                    'user' => $user,
                ]);

            $this->mailer->send($email);

            // 2. Send SMS if phone is available
            if ($user->getTelephoneUser()) {
                $this->sendParticipationSms($participation);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendParticipationSms(Participation $participation): bool
    {
        $user = $participation->getUser();
        $evenement = $participation->getEvenement();

        if (!$user || !$user->getTelephoneUser()) {
            return false;
        }

        try {
            $message = sprintf(
                "Bonjour %s, votre participation à l'événement '%s' est confirmée ! Merci de votre confiance. Horizon.",
                $user->getFirstName(),
                $evenement->getTitreEvent()
            );

            $sms = new SmsMessage(
                $user->getTelephoneUser(),
                $message
            );

            $this->texter->send($sms);
            return true;
        } catch (\Exception $e) {
            // We don't want to break the whole flow if SMS fails
            return false;
        }
    }
}
