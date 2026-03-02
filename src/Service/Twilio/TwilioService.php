<?php

namespace App\Service\Twilio;

use Twilio\Rest\Client;
use Twilio\Http\CurlClient;

class TwilioService
{
    private Client $client;
    private string $fromNumber;
    private string $verifyServiceSid;

    public function __construct(string $accountSid, string $authToken, string $fromNumber, ?string $verifyServiceSid = null)
    {
        $accountSid = trim($accountSid);
        $authToken = trim($authToken);
        $fromNumber = trim($fromNumber);
        $this->verifyServiceSid = $verifyServiceSid ? trim($verifyServiceSid) : '';

        // Use a custom CurlClient to bypass SSL verification issues in local development environments (Windows/WAMP)
        $httpClient = new CurlClient([
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $this->client = new Client($accountSid, $authToken, null, null, $httpClient);
        $this->fromNumber = $fromNumber;
    }

    /**
     * Send an SMS message
     */
    public function sendSms(string $to, string $message): void
    {
        $toNormalized = str_replace([' ', '-', '(', ')'], '', trim($to));

        // Only normalize 'from' if it looks like a phone number (starts with +)
        $fromRaw = trim($this->fromNumber);
        $fromNormalized = str_starts_with($fromRaw, '+') ? str_replace([' ', '-', '(', ')'], '', $fromRaw) : $fromRaw;

        if (str_starts_with($fromRaw, '+') && $toNormalized === $fromNormalized) {
            throw new \RuntimeException("Twilio Error: 'To' and 'From' number cannot be the same ($to). Please use a different phone number for your user profile for testing.");
        }

        try {
            $this->client->messages->create(
                $to,
                [
                    'from' => $this->fromNumber,
                    'body' => $message,
                ]
            );
        } catch (\Twilio\Exceptions\RestException $e) {
            if ($e->getCode() === 21601) {
                throw new \RuntimeException("Twilio Error: 'To' and 'From' number cannot be the same. Please use a different phone number for testing.");
            }
            throw new \RuntimeException("Twilio Error: " . $e->getMessage());
        }
    }

    /**
     * Make a voice call (example of "doing some stuff with it")
     */
    public function makeCall(string $to, string $twimlUrl): void
    {
        $this->client->calls->create(
            $to,
            $this->fromNumber,
            [
                "url" => $twimlUrl
            ]
        );
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Send a verification code using Twilio Verify
     */
    public function sendVerification(string $to): void
    {
        if (empty($this->verifyServiceSid)) {
            throw new \RuntimeException("Twilio Verify Error: TWILIO_VERIFY_SERVICE_SID is not configured in .env. Please configure it or use the standard SMS channel.");
        }

        try {
            $this->client->verify->v2->services($this->verifyServiceSid)
                ->verifications
                ->create($to, "sms");
        } catch (\Twilio\Exceptions\RestException $e) {
            throw new \RuntimeException("Twilio Verify Error: " . $e->getMessage());
        }
    }

    /**
     * Check a verification code using Twilio Verify
     */
    public function checkVerification(string $to, string $code): bool
    {
        if (empty($this->verifyServiceSid)) {
            return false;
        }

        try {
            $verificationCheck = $this->client->verify->v2->services($this->verifyServiceSid)
                ->verificationChecks
                ->create([
                    "to" => $to,
                    "code" => $code
                ]);

            return $verificationCheck->status === 'approved';
        } catch (\Twilio\Exceptions\RestException $e) {
            return false;
        }
    }
}
