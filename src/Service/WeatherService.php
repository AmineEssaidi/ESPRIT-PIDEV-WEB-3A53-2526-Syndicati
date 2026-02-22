<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Get weather forecast for a specific location and date
     * Uses Open-Meteo API (Free, no key required)
     */
    public function getForecast(float $lat, float $lng, \DateTimeInterface $date): ?array
    {
        try {
            // Open-Meteo Forecast API
            // Note: For dates in the future, it gives forecasts. For past dates, you need historical API.
            // Here we use the forecast API which handles up to 16 days.
            $url = sprintf(
                'https://api.open-meteo.com/v1/forecast?latitude=%f&longitude=%f&daily=temperature_2m_max,temperature_2m_min,weathercode,windspeed_10m_max&timezone=auto',
                $lat,
                $lng
            );

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            if (!isset($data['daily'])) {
                return null;
            }

            $targetDate = $date->format('Y-m-d');
            $dateIndex = array_search($targetDate, $data['daily']['time']);

            if ($dateIndex === false) {
                // If specific date is not in range, return current day as fallback or null
                return [
                    'message' => 'Forecast not available for this specific date (too far in future or past).',
                    'fallback' => true,
                    'temp_max' => $data['daily']['temperature_2m_max'][0],
                    'temp_min' => $data['daily']['temperature_2m_min'][0],
                    'condition' => $this->getConditionFromCode($data['daily']['weathercode'][0]),
                    'icon' => $this->getIconFromCode($data['daily']['weathercode'][0]),
                    'wind' => $data['daily']['windspeed_10m_max'][0]
                ];
            }

            return [
                'temp_max' => $data['daily']['temperature_2m_max'][$dateIndex],
                'temp_min' => $data['daily']['temperature_2m_min'][$dateIndex],
                'condition' => $this->getConditionFromCode($data['daily']['weathercode'][$dateIndex]),
                'icon' => $this->getIconFromCode($data['daily']['weathercode'][$dateIndex]),
                'wind' => $data['daily']['windspeed_10m_max'][$dateIndex]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getConditionFromCode(int $code): string
    {
        $codes = [
            0 => 'Clear sky',
            1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
            45 => 'Fog', 48 => 'Depositing rime fog',
            51 => 'Light drizzle', 53 => 'Moderate drizzle', 55 => 'Dense drizzle',
            61 => 'Slight rain', 63 => 'Moderate rain', 65 => 'Heavy rain',
            71 => 'Slight snow', 73 => 'Moderate snow', 75 => 'Heavy snow',
            80 => 'Slight rain showers', 81 => 'Moderate rain showers', 82 => 'Violent rain showers',
            95 => 'Thunderstorm', 96 => 'Thunderstorm with slight hail', 99 => 'Thunderstorm with heavy hail'
        ];
        return $codes[$code] ?? 'Unknown';
    }

    private function getIconFromCode(int $code): string
    {
        // Mapping Open-Meteo codes to Boxicons or Emoji
        if ($code == 0) return 'bx-sun';
        if ($code <= 3) return 'bx-cloud';
        if ($code <= 48) return 'bx-cloud-light-rain';
        if ($code <= 55) return 'bx-cloud-drizzle';
        if ($code <= 65) return 'bx-cloud-rain';
        if ($code <= 75) return 'bx-cloud-snow';
        if ($code <= 82) return 'bx-cloud-lightning';
        return 'bx-cloud-lightning';
    }
}
