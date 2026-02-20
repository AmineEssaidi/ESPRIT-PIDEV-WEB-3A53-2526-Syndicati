<?php

namespace App\Command;

use App\Repository\Residence\AppartementRepository;
use App\Service\MaintenancePrediction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:predict-maintenance', description: 'Generate maintenance predictions for apartments')]
class PredictionMaintenanceCommand extends Command
{
    public function __construct(
        private AppartementRepository $appartementRepository,
        private MaintenancePrediction $predictor,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('appartement-id', InputArgument::REQUIRED, 'The apartment ID to predict maintenance for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appartementId = $input->getArgument('appartement-id');
        
        $appartement = $this->appartementRepository->find($appartementId);
        if (!$appartement) {
            $output->writeln("Apartment {$appartementId} not found.");
            return Command::FAILURE;
        }

        $maintenance = $appartement->getMaintenance();
        if (!$maintenance) {
            $output->writeln("No maintenance record for apartment {$appartementId} — skipping.");
            return Command::FAILURE;
        }

        if (!$this->isMaintenanceSufficientlyFilled($maintenance)) {
            $output->writeln("Insufficient maintenance data for apartment {$appartementId} — skipping.");
            $maintenance->setRecommendationIa('Données insuffisantes pour une analyse.');
            $this->em->flush();
            return Command::SUCCESS;
        }

        try {
            $output->writeln("Calling Gemini API for apartment {$appartementId}...");
            $recommendation = $this->predictor->predict($appartement);
            $maintenance->setRecommendationIa($recommendation);
            $this->em->flush();
            $output->writeln("Prediction saved: {$recommendation}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("Prediction failed: " . $e->getMessage());
            $maintenance->setRecommendationIa('Prediction unavailable');
            $this->em->flush();
            return Command::FAILURE;
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