<?php

namespace App\Service\Twilio;

use Twilio\Rest\Client;
use Twilio\Http\CurlClient;

class TwilioService
{
    private Client $client;
    private string $fromNumber;

    public function __construct(string $accountSid, string $authToken, string $fromNumber)
    {
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
        if ($to === $this->fromNumber) {
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
}
