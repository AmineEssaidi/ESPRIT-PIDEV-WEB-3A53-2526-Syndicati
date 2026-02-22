<?php

namespace App\Service;

use App\Entity\Forum\Publication;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class MailNotificationService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendNewPublicationNotification(Publication $publication): void
    {
        $toEmail = $_ENV['MAILER_TO_EMAIL'] ?? 'rayenkahloun15@gmail.com';
        $fromEmail = $_ENV['MAILER_FROM_EMAIL'] ?? $toEmail;
        $fromName = $_ENV['MAILER_FROM_NAME'] ?? 'Horizon Platform';

        $email = (new TemplatedEmail())
            ->from(new Address($fromEmail, $fromName))
            ->to($toEmail)
            ->subject('New Publication Alert: ' . $publication->getTitrePub())
            ->htmlTemplate('emails/new_publication.html.twig')
            ->context([
                'publication' => $publication,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error to a specific file for debugging
            $logMessage = sprintf("[%s] Mailer Error: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
            file_put_contents(__DIR__ . '/../../var/log/mailer_error.log', $logMessage, FILE_APPEND);
            // We don't re-throw because we don't want to break the publication creation
        }
    }
}
