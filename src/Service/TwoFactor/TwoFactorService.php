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
        private readonly \App\Service\Twilio\TwilioService $twilioService,
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

    public function sendCode(User $user, string $channel = 'email'): string
    {
        if ($channel === 'sms') {
            try {
                // Attempt to send via Twilio Verify (Preferred)
                return $this->sendSms($user);
            } catch (\RuntimeException $e) {
                // If it's a configuration error (missing SID), we fall back to standard SMS
                // If it's an API error (Trial restriction, invalid number), we catch it below
                if (str_contains($e->getMessage(), 'not configured')) {
                    // Fallback to manual code generation + standard SMS
                    $code = $this->generateCode();
                    $user->setAuthCode($code);
                    $user->setAuthCodeExpiresAt((new \DateTime())->modify('+15 minutes'));
                    $this->em->flush();

                    try {
                        $this->twilioService->sendSms($user->getPhone(), sprintf('Your verification code is: %s. Valid for 15 minutes.', $code));
                        return 'SENT_VIA_SMS_FALLBACK';
                    } catch (\Exception $smsEx) {
                        throw new \RuntimeException("SMS Fallback failed: " . $smsEx->getMessage());
                    }
                }

                // If it was an actual Twilio API error during Verify attempt, propagate it
                throw $e;
            }
        }

        $code = $this->generateCode();
        $user->setAuthCode($code);
        $user->setAuthCodeExpiresAt((new \DateTime())->modify('+15 minutes'));
        $this->em->flush();

        return $this->sendEmail($user, $code);
    }

    private function sendEmail(User $user, string $code): string
    {
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

    private function sendSms(User $user): string
    {
        $phone = $user->getPhone();

        if (!$phone) {
            throw new \RuntimeException('No phone number configured.');
        }

        $this->twilioService->sendVerification($phone);

        return 'SENT_VIA_SMS';
    }

    /**
     * Verify OTP code for user
     */
    public function verifyCode(User $user, string $code): bool
    {
        // 1. Try internal verification (Email codes)
        if ($user->isAuthCodeValid() && $user->getAuthCode() === $code) {
            $user->setAuthCode(null);
            $user->setAuthCodeExpiresAt(null);
            $this->em->flush();
            return true;
        }

        // 2. Try Twilio Verify (SMS codes)
        $phone = $user->getPhone();
        if ($phone && $this->twilioService->checkVerification($phone, $code)) {
            return true;
        }

        return false;
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
