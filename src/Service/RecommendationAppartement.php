<?php

namespace App\Service;

use App\Entity\Residence\Appartement;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationAppartement
{
        public function __construct(private EntityManagerInterface $em) {}
    public function AppartementsSimilaires(Appartement $apartment, int $limit = 5): array
    {
        $referenceType = $apartment->getTypeA();
        $referencePrice = $apartment->getPrixLocation();
        $surface = $apartment->getSuperficie();
        $allApartments = $this->em->getRepository(Appartement::class)
            ->findAll();
        $scores = [];
        foreach ($allApartments as $apt) {
            if ($apt->getIdApp() === $apartment->getIdApp()) {
                continue;
            }
            $score = $this->ScoreAppartement(
                $referenceType,
                $referencePrice,
                $surface,
                $apt->getTypeA(),
                $apt->getPrixLocation() ?? 0,
                $apt->getSuperficie() ?? 0
            );
            $scores[$apt->getIdApp()] = [
                'apartment' => $apt,
                'score' => $score
            ];
        }
        usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $unique = [];
        $seenIds = [];
        foreach ($scores as $item) {
            $id = $item['apartment']->getIdApp();
            if (!isset($seenIds[$id])) {
                $unique[] = $item;
                $seenIds[$id] = true;
                if (count($unique) >= $limit) {
                    break;
                }
            }
        }
        
        return $unique;
    }


    private function ScoreAppartement(
        string $TypeA,
        float $prix,
        float $surface,
        string $appartementtype,
        float $aptPrice,
        float $aptSurface
    ): float {
        $score = 0;

        if ($TypeA === $appartementtype) {
            $score += 40;
        } else {
            $TypeANum = $this->extraire($TypeA);
            $numtype = $this->extraire($appartementtype);
            
            if ($TypeANum !== null && $numtype !== null) {
                $typeDiff = abs($TypeANum - $numtype);
                if ($typeDiff === 1) {
                    $score += 20;
                }
            }
        }
        if ($prix > 0) {
            $DifferencePrix = abs($prix - $aptPrice) / $prix;
            
            switch ($DifferencePrix)
            {
                case ($DifferencePrix<=0.1):
                    $score+=35;
                    break;
                case ($DifferencePrix<=0.2):
                    $score+=25;
                    break;
                case ($DifferencePrix<=0.3):
                    $score+=15;
                    break;
                case ($DifferencePrix<=0.5):
                    $score+=5;
                    break;
            }

        }
        if ($surface > 0) 
        {
            $surfaceDiff = abs($surface - $aptSurface) / $surface;

            switch ($surfaceDiff)
            {
                case ($surfaceDiff<=0.1):
                    $score+=25;
                    break;
                case ($surfaceDiff<=0.2):
                $score+=15;
                break;
                case ($surfaceDiff<=0.3):
                $score+=10;
                break;
                case ($surfaceDiff<=0.5):
                $score+=5;
                break;
            }
            
        }

        return $score;
    }

    private function extraire(string $type): ?int
    {
        if (preg_match('/S\+(\d+)/', $type, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}