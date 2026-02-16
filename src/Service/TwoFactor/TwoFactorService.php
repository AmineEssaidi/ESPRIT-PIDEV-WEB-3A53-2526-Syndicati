<?php

namespace App\Service\TwoFactor;

use App\Entity\User\User;
use App\Service\OAuth\GmailOAuthMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class TwoFactorService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $em,
        private readonly GmailOAuthMailer $gmailOAuthMailer,
        private readonly string $fromEmail = 'noreply@horizon.local',
        private readonly string $fromName = 'Horizon',
        private readonly string $mailerDsn = 'null://null'
    ) {
    }

    /**
     * Generate a 6-digit OTP code
     */
    public function generateCode(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP code to the user's email address (from the user table).
     * No user has to connect Gmail; the recipient is always $user->getEmailUser().
     */
    public function sendCode(User $user): string
    {
        $code = $this->generateCode();
        
        // Store code in user entity (expires in 15 minutes)
        $user->setAuthCode($code);
        $user->setAuthCodeExpiresAt((new \DateTime())->modify('+15 minutes'));
        $this->em->flush();

        // Send to the user's email from the user table. Sender = SMTP (MAILER_DSN) or Gmail OAuth.
        $message = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($user->getEmailUser())
            ->subject('Your Two-Factor Authentication Code')
            ->html($this->twig->render('emails/two_factor.html.twig', [
                'user' => $user,
                'code' => $code,
            ]));

        $dsn = $this->mailerDsn;
        $smtpConfigured = $dsn !== '' && !str_starts_with($dsn, 'null://');

        // Prefer SMTP from .env – no Gmail connection in profile needed
        if ($smtpConfigured) {
            $this->mailer->send($message);
            return $code;
        }
        if ($this->gmailOAuthMailer->isAvailable()) {
            $this->gmailOAuthMailer->send($message);
            return $code;
        }

        throw new \RuntimeException(
            'Email is not configured. Set MAILER_DSN in .env to your SMTP (e.g. Gmail: smtp://you@gmail.com:YOUR_APP_PASSWORD@smtp.gmail.com:587). No Gmail connection in profile needed.'
        );
    }

    /**
     * Verify OTP code for user
     */
    public function verifyCode(User $user, string $code): bool
    {
        if (!$user->isAuthCodeValid()) {
            return false;
        }

        if ($user->getAuthCode() !== $code) {
            return false;
        }

        // Clear code after successful verification
        $user->setAuthCode(null);
        $user->setAuthCodeExpiresAt(null);
        $this->em->flush();

        return true;
    }

    /**
     * Clear any existing auth code (e.g., on logout or after timeout)
     */
    public function clearCode(User $user): void
    {
        $user->setAuthCode(null);
        $user->setAuthCodeExpiresAt(null);
        $this->em->flush();
    }
}
