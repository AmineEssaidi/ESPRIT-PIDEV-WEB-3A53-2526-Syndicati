<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;

class EventRecommendationService
{
    /**
     * Mirrors the Java event recommendation logic: favorite event types first,
     * then popularity, then newer events as a gentle tie-breaker.
     *
     * @param Evenement[] $events
     * @param Participation[] $history
     * @return Evenement[]
     */
    public function recommend(array $events, array $history): array
    {
        $favoriteTypes = [];
        $seenEventIds = [];

        foreach ($history as $participation) {
            $event = $participation->getEvenement();
            if (!$event) {
                continue;
            }

            $seenEventIds[$event->getId()] = true;
            $type = $event->getTypeEvent();
            if ($type) {
                $favoriteTypes[$type] = ($favoriteTypes[$type] ?? 0) + 1;
            }
        }

        $unique = [];
        foreach ($events as $event) {
            if (!$event instanceof Evenement || !$event->getId() || isset($seenEventIds[$event->getId()])) {
                continue;
            }
            $unique[$event->getId()] = $event;
        }

        $recommendations = array_values($unique);
        usort($recommendations, function (Evenement $a, Evenement $b) use ($favoriteTypes): int {
            return $this->score($b, $favoriteTypes) <=> $this->score($a, $favoriteTypes);
        });

        return array_slice($recommendations, 0, 4);
    }

    /**
     * @param array<string,int> $favoriteTypes
     */
    private function score(Evenement $event, array $favoriteTypes): float
    {
        $score = 0.0;
        $type = $event->getTypeEvent();

        if ($type && isset($favoriteTypes[$type])) {
            $score += 10.0 * $favoriteTypes[$type];
        }

        $places = max(0, (int) $event->getNbPlaces());
        $remaining = max(0, (int) $event->getNbRestants());
        if ($places > 0) {
            $score += (($places - $remaining) / $places) * 5.0;
        }

        $score += ((int) $event->getId()) * 0.01;

        return $score;
    }
}
