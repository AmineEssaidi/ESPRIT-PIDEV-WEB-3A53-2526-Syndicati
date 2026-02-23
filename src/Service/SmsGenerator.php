<?php
namespace App\Service;

use App\Service\Twilio\TwilioService;

class SmsGenerator
{
    private TwilioService $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    public function SendSms(string $number, string $name, string $text)
    {
        $message = $name . ' veut louer votre appartement \n ' . $text;
        $this->twilioService->sendSms($number, $message);
    }
}
