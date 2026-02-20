<?php

namespace App\Command;

use App\Service\MachineLearning;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TrainPrixCommand extends Command
{
    protected static $defaultName = 'app:train-prix-appartement';
    protected static $defaultDescription = 'Train the apartment rental price prediction model from CSV data';
    
    private MachineLearning $predictor;
    private ParameterBagInterface $params;
    
    public function __construct(
        MachineLearning $predictor,
        ParameterBagInterface $params
    ) {
        $this->predictor = $predictor;
        $this->params = $params;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'model',
            'm',
            InputOption::VALUE_OPTIONAL,
            'Path where to save the trained model',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Prédiction des prix des appartements');
        
        try {
            $io->section("Training model from CSV: public/tunisia-real-estate.csv");
            $this->predictor->trainFromCsv();
            $io->success('Model trained successfully');
        } catch (\Exception $e) {
            $io->error('Training failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Save model
        $modelPath = $input->getOption('model');
        
        if (!$modelPath) {
            $projectDir = $this->params->get('kernel.project_dir');
            $modelDir = $projectDir . '/var/models';
            $modelPath = $modelDir . '/appartement_prix.model';
        }

        try {
            $io->note("Saving model to: $modelPath");
            $this->predictor->saveModel($modelPath);
        } catch (\Exception $e) {
            $io->error('Failed to save model: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (file_exists($modelPath)) {
            $io->success("Model trained and saved successfully to: $modelPath");
            return Command::SUCCESS;
        } else {
            $io->error('Model file was not created');
            return Command::FAILURE;
        }
    }
}