<?php

namespace App\Service\TwoFactor;

use App\Entity\User\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Email mailer for sending 2FA OTP codes.
 * This service is kept for potential future integration with scheb/2fa-bundle.
 */
class EmailAuthCodeMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $fromEmail = 'noreply@horizon.local',
        private readonly string $fromName = 'Horizon'
    ) {
    }

    public function sendAuthCode(User $user, string $code): void
    {
        $message = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($user->getEmailUser())
            ->subject('Your Two-Factor Authentication Code')
            ->html($this->twig->render('emails/two_factor.html.twig', [
                'user' => $user,
                'code' => $code,
            ]));

        $this->mailer->send($message);
    }
}
