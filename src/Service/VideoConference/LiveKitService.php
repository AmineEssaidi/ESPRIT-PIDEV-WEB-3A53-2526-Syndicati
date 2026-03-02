<?php

namespace App\Service\VideoConference;

use Firebase\JWT\JWT;

class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $liveKitUrl;

    public function __construct(string $apiKey, string $apiSecret, string $liveKitUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->liveKitUrl = $liveKitUrl;
    }

    /**
     * Generates an access token for a LiveKit room.
     */
    public function generateToken(string $roomName, string $identity, string $name = ''): string
    {
        $payload = [
            'iss' => $this->apiKey,
            'sub' => $identity,
            'nbf' => time() - 60, // 1 minute buffer for clock skew
            'exp' => time() + (2 * 3600), // Valid for 2 hours
            'video' => [
                'roomCreate' => true,
                'roomJoin' => true,
                'room' => $roomName,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
            'name' => (string) ($name ?: $identity),
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    public function getLiveKitUrl(): string
    {
        return $this->liveKitUrl;
    }
}
