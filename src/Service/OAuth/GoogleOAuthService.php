<?php

namespace App\Service\OAuth;

use App\Entity\OAuth\OAuth;
use App\Entity\User\User;
use App\Repository\OAuth\OAuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles Google OAuth2 flow and token storage in the OAUTH table.
 */
class GoogleOAuthService
{
    public const GMAIL_SCOPE = 'https://www.googleapis.com/auth/gmail.send';
    public const USER_INFO_SCOPES = [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    public function __construct(
        private readonly OAuthRepository $oauthRepository,
        private readonly EntityManagerInterface $em,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function createClient(?string $redirectUri = null): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($redirectUri ?? $this->redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([self::GMAIL_SCOPE]);

        // Fix for local SSL issues (cURL error 60)
        $httpClient = new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 10.0,
        ]);
        $client->setHttpClient($httpClient);

        return $client;
    }

    public function createLoginClient(?string $redirectUri = null): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($redirectUri ?? $this->redirectUri);
        $client->setScopes(self::USER_INFO_SCOPES);

        // Fix for local SSL issues (cURL error 60)
        $httpClient = new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 10.0,
        ]);
        $client->setHttpClient($httpClient);

        return $client;
    }

    /** Generate the URL to redirect the user to Google consent screen. Pass $redirectUri to match your current site URL (avoids redirect_uri_mismatch). */
    public function getAuthorizationUrl(?string $state = null, ?string $redirectUri = null): string
    {
        $client = $this->createClient($redirectUri);
        if ($state !== null) {
            $client->setState($state);
        }
        return $client->createAuthUrl();
    }

    public function getLoginAuthorizationUrl(?string $state = null, ?string $redirectUri = null): string
    {
        $client = $this->createLoginClient($redirectUri);
        if ($state !== null) {
            $client->setState($state);
        }
        return $client->createAuthUrl();
    }

    /**
     * Exchange authorization code for tokens and store in OAUTH table for the given user.
     * Pass $redirectUri if you used a custom redirect URI for the auth request (must match exactly).
     */
    public function exchangeCodeAndStore(User $user, string $code, ?string $redirectUri = null): OAuth
    {
        $client = $this->createClient($redirectUri);
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error']);
        }

        $oauth = $this->oauthRepository->findOneByUser($user) ?? new OAuth();
        $oauth->setUser($user);
        $oauth->setAccessToken($token['access_token']);
        $oauth->setRefreshToken($token['refresh_token'] ?? $oauth->getRefreshToken());
        $oauth->setTokenType($token['token_type'] ?? 'Bearer');
        $oauth->setScope(isset($token['scope']) ? (is_array($token['scope']) ? implode(' ', $token['scope']) : $token['scope']) : null);

        $expiresIn = (int) ($token['expires_in'] ?? 3600);
        $oauth->setExpiresAt((new \DateTime())->modify("+{$expiresIn} seconds"));

        $this->em->persist($oauth);
        $this->em->flush();

        return $oauth;
    }

    /**
     * Refresh the access token using refresh_token and update the entity.
     */
    public function refreshToken(OAuth $oauth): void
    {
        $client = $this->createClient();
        $client->setAccessToken([
            'access_token' => $oauth->getAccessToken(),
            'refresh_token' => $oauth->getRefreshToken(),
            'expires_in' => 0,
        ]);
        $client->fetchAccessTokenWithRefreshToken($oauth->getRefreshToken());

        $token = $client->getAccessToken();
        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error']);
        }

        $oauth->setAccessToken($token['access_token']);
        $oauth->setTokenType($token['token_type'] ?? 'Bearer');
        $expiresIn = (int) ($token['expires_in'] ?? 3600);
        $oauth->setExpiresAt((new \DateTime())->modify("+{$expiresIn} seconds"));

        $this->em->flush();
    }

    /** Get a valid (possibly refreshed) OAuth entity for the user, or null */
    public function getValidOAuthForUser(User $user): ?OAuth
    {
        $oauth = $this->oauthRepository->findOneByUserAndScope($user, 'gmail');
        if ($oauth === null) {
            return null;
        }
        if ($oauth->isExpired()) {
            $this->refreshToken($oauth);
        }
        return $oauth;
    }

    /** Get a valid OAuth entity by user id (e.g. system mailer user). */
    public function getValidOAuthByUserId(int $userId): ?OAuth
    {
        $oauth = $this->oauthRepository->findOneByUserId($userId);
        if ($oauth === null) {
            return null;
        }
        if ($oauth->isExpired()) {
            $this->refreshToken($oauth);
        }
        return $oauth;
    }

    /** Get a valid OAuth entity (any user). Used when no specific MAILER_OAUTH_USER_ID is set. */
    public function getValidOAuthAny(): ?OAuth
    {
        $oauth = $this->oauthRepository->findOneAny();
        if ($oauth === null) {
            return null;
        }
        if ($oauth->isExpired()) {
            try {
                $this->refreshToken($oauth);
            } catch (\Throwable) {
                return null;
            }
        }
        return $oauth;
    }

    public function getUserInfo(string $code, ?string $redirectUri = null): array
    {
        $logFile = dirname(__DIR__, 3) . '/public/google_auth.log';
        $log = function ($msg) use ($logFile) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] [Service] " . $msg . "\n", FILE_APPEND);
        };

        $log("getUserInfo started with code: " . substr($code, 0, 10) . "...");

        try {
            if (!class_exists('\Google\Client')) {
                $log("ERROR: \Google\Client class NOT FOUND!");
                throw new \RuntimeException("\Google\Client class not found. Check composer install.");
            }

            $client = $this->createLoginClient($redirectUri);
            $log("Client created. Fetching access token...");

            $token = $client->fetchAccessTokenWithAuthCode($code);
            $log("Token response received: " . (isset($token['error']) ? "ERROR: " . json_encode($token) : "SUCCESS (token received)"));

            if (isset($token['error'])) {
                throw new \RuntimeException($token['error_description'] ?? $token['error']);
            }

            $log("Initializing Oauth2 service...");
            $service = new \Google\Service\Oauth2($client);

            $log("Requesting userinfo from Google...");
            $userInfo = $service->userinfo->get();
            $log("User info received from Google: " . ($userInfo ? "YES (ID: " . $userInfo->id . ")" : "NO"));

            return [
                'id' => $userInfo->id,
                'email' => $userInfo->email,
                'firstName' => $userInfo->givenName,
                'lastName' => $userInfo->familyName,
                'picture' => $userInfo->picture,
            ];
        } catch (\Throwable $e) {
            $log("SERVICE ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }
}
