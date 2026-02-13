<?php

namespace App\Command;
use App\Service\MachineLearning;
use App\Repository\Residence\AppartementRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class TrainPrixCommand extends Command
{

    protected static $defaultName = 'app:train-prix-appartement';
    
    private $AppartementRepository;
    private $predictor;
        private $params;
    
     public function __construct(
        AppartementRepository $AppartementRepository, 
        MachineLearning $predictor,
        ParameterBagInterface $params
    ) {
        $this->AppartementRepository = $AppartementRepository;
        $this->predictor = $predictor;
        $this->params = $params;
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Prédiction des prix des appartements');
        
        $appartements = $this->AppartementRepository->findAll();
        $samples = [];
        $prixLocation = [];
        foreach ($appartements as $appartement) {
            $samples[] = $appartement;
            $prixLocation[] = $appartement->getPrixLocation();
        }
        
        $io->section('Training model with ' . count($appartements) . ' apartments');
        $this->predictor->train($samples, $prixLocation);
        

        $projectDir = $this->params->get('kernel.project_dir');
        $modelDir = $projectDir . '/var/models';
        $modelPath = $modelDir . '/appartement_prix.model';
        
        $io->note('Saving model to: ' . $modelPath);
        $this->predictor->saveModel($modelPath);
        
        
    if (file_exists($modelPath)) {
        $io->success('Model entrainé avec succès ' . $modelPath);
    }
        
        return Command::SUCCESS;
    }
}
