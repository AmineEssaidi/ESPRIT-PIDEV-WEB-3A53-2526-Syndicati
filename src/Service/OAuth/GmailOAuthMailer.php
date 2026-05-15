<?php

namespace App\Service\OAuth;

use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Symfony\Component\Mime\Email;

/**
 * Sends email via Gmail API using OAuth2 tokens stored in the OAUTH table.
 * Use MAILER_OAUTH_USER_ID to specify which user's Gmail connection to use (e.g. system mailbox).
 */
class GmailOAuthMailer
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly ?int $oauthUserId,
        private readonly string $fromName = 'Syndicati',
    ) {
    }

    /**
     * Check if Gmail OAuth is configured and we have a valid token to send with.
     * If MAILER_OAUTH_USER_ID is set, only that user's connection is used; otherwise any connected Gmail is used.
     */
    public function isAvailable(): bool
    {
        return $this->resolveOAuth() !== null;
    }

    private function resolveOAuth(): ?\App\Entity\OAuth\OAuth
    {
        if ($this->oauthUserId !== null && $this->oauthUserId > 0) {
            return $this->googleOAuthService->getValidOAuthByUserId($this->oauthUserId);
        }
        return $this->googleOAuthService->getValidOAuthAny();
    }

    /**
     * Send an email using Gmail API with the configured OAuth user's tokens.
     */
    public function send(Email $email): void
    {
        $oauth = $this->resolveOAuth();

        if ($oauth === null) {
            throw new \RuntimeException('Gmail OAuth is not configured or no valid token. Connect Gmail in Profile → Two-Factor Authentication → "Gmail for sending app emails", or set MAILER_OAUTH_USER_ID in .env to the user id that connected Gmail.');
        }

        $client = $this->googleOAuthService->createClient();
        $client->setAccessToken([
            'access_token' => $oauth->getAccessToken(),
            'refresh_token' => $oauth->getRefreshToken(),
            'expires_in'    => $oauth->getExpiresAt() ? ($oauth->getExpiresAt()->getTimestamp() - time()) : 0,
        ]);

        $gmail = new Gmail($client);
        $raw = $this->emailToRawMessage($email);
        $message = new Message();
        $message->setRaw($raw);

        $gmail->users_messages->send('me', $message);
    }

    /**
     * Build a RFC 2822 raw message and base64url-encode it for Gmail API.
     */
    private function emailToRawMessage(Email $email): string
    {
        $headers = $email->getHeaders();
        $from = $email->getFrom();
        $to = $email->getTo();
        $subject = $email->getSubject();
        $body = $email->getHtmlBody() ?? $email->getTextBody() ?? '';

        $headers->addTextHeader('MIME-Version', '1.0');
        $headers->addTextHeader('Content-Type', 'text/html; charset=UTF-8');

        $raw = "From: {$this->formatAddresses($from)}\r\n";
        $raw .= "To: {$this->formatAddresses($to)}\r\n";
        $raw .= "Subject: {$subject}\r\n";
        $raw .= $headers->toString();
        $raw .= "\r\n\r\n";
        $raw .= $body;

        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($raw));
    }

    private function formatAddresses(array $addresses): string
    {
        $parts = [];
        foreach ($addresses as $addr) {
            $parts[] = $addr->getName() ? "{$addr->getName()} <{$addr->getAddress()}>" : $addr->getAddress();
        }
        return implode(', ', $parts);
    }
}
