<?php

namespace App\Message;

use App\Repository\Residence\AppartementRepository;
use App\Service\MaintenancePrediction;
use Doctrine\ORM\EntityManagerInterface;

class GenerateMaintenancePrediction
{
    public function __construct(
        public readonly int $appartementId
    ) {}
}

class GenerateMaintenancePredictionHandler
{
    public function __construct(
        private AppartementRepository $appartementRepository,
        private MaintenancePrediction $predictor,
        private EntityManagerInterface $em
    ) {}

    public function __invoke(GenerateMaintenancePrediction $message): void
    {
        $appartementId = $message->appartementId;
        
        $appartement = $this->appartementRepository->find($appartementId);
        if (!$appartement) {
            error_log("Apartment not found: {$appartementId}");
            return;
        }

        $maintenance = $appartement->getMaintenance();
        if (!$maintenance) {
            error_log("No maintenance record for apartment: {$appartementId} — skipping.");
            return;
        }

        if (!$this->isMaintenanceSufficientlyFilled($maintenance)) {
            error_log("Insufficient data for apartment: {$appartementId} — skipping.");
            $maintenance->setRecommendationIa('Données insuffisantes pour une analyse.');
            $this->em->flush();
            return;
        }

        try {
            error_log("Predicting for apartment: {$appartementId}");
            $recommendation = $this->predictor->predict($appartement);
            $maintenance->setRecommendationIa($recommendation);
            $this->em->flush();
            error_log("Prediction saved for apartment: {$appartementId}");
        } catch (\Exception $e) {
            error_log("Prediction failed for apartment {$appartementId}: " . $e->getMessage());
            $maintenance->setRecommendationIa('Prediction unavailable');
            $this->em->flush();
        }
    }

    private function isMaintenanceSufficientlyFilled($maintenance): bool
    {
        $fields = [
            $maintenance->getEtatApp(),
            $maintenance->getEtatPlomberie(),
            $maintenance->getEtatElectricite(),
            $maintenance->getEtatChauffage(),
            $maintenance->getDateDerniereMaintenance(),
            $maintenance->getDescriptionMaint(),
        ];
        $filled = array_filter($fields, fn($v) => $v !== null && $v !== '');
        return count($filled) >= 4;
    }
}