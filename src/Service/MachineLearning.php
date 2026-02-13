<?php

namespace App\Service;
use Phpml\Regression\LeastSquares;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Preprocessing\Normalizer;

class MachineLearning
{
   private NaiveBayes $classifier;
private TokenCountVectorizer $vectorizer;

public function __construct()
{
    $this->regressor = new LeastSquares();
    $this->normalizer = new Normalizer();
}

public function train(array $appartements, array $prixLocation): void
{
    $filteredAppartements = [];
    $filteredPrixLocation = [];
    $skippedCount = 0;
    
    foreach ($appartements as $index => $appartement) {
        $superficie = $appartement->getSuperficie();
        $typeA = $appartement->getTypeA();
        
        if ($superficie === null || $superficie === '' || (float) $superficie <= 0) {
            error_log('Skipping appartment - invalid superficie: ' . ($superficie ?? 'null'));
            $skippedCount++;
            continue;
        }
        
        if ($typeA === null || $typeA === '') {
            error_log('Skipping appartment - invalid type: ' . ($typeA ?? 'null'));
            $skippedCount++;
            continue;
        }
        
        $typeMap = [
            'S+0' => 0,
            'S+1' => 1,
            'S+2' => 2,
            'S+3' => 3,
            'S+4' => 4,
            'S+5' => 5,
        ];
        
        if (!isset($typeMap[strtoupper($typeA)])) {
            error_log('Skipping appartment - unknown type: ' . $typeA);
            $skippedCount++;
            continue;
        }
        
        if (!isset($prixLocation[$index]) || $prixLocation[$index] === null || (float) $prixLocation[$index] <= 0) {
            error_log('Skipping appartment - invalid price: ' . ($prixLocation[$index] ?? 'null'));
            $skippedCount++;
            continue;
        }
        
        $filteredAppartements[] = $appartement;
        $filteredPrixLocation[] = $prixLocation[$index];
    }
    
    error_log('Training: ' . count($filteredAppartements) . ' valid appartments used, ' . $skippedCount . ' skipped due to null/invalid values');
    
    if (empty($filteredAppartements)) {
        throw new \Exception('No valid appartments with complete data for training');
    }
    
    $features = [];
    foreach ($filteredAppartements as $appartement) {
        $features[] = $this->extractFeatures($appartement);
    }
    
    $this->normalizer->fit($features);
    $this->normalizer->transform($features);
    
    $this->regressor->train($features, $filteredPrixLocation);
}

public function predict($appartement): float
{
    try {
        $superficie = $appartement->getSuperficie();
        $typeA = $appartement->getTypeA();
        
        if ($superficie === null || $superficie === '' || (float) $superficie <= 0) {
            error_log('Prediction error: Invalid superficie');
            return 0.0;
        }
        
        if ($typeA === null || $typeA === '') {
            error_log('Prediction error: Invalid type');
            return 0.0;
        }
        
        $typeMap = [
            'S+0' => 0,
            'S+1' => 1,
            'S+2' => 2,
            'S+3' => 3,
            'S+4' => 4,
            'S+5' => 5,
        ];
        
        if (!isset($typeMap[strtoupper($typeA)])) {
            error_log('Prediction error: Unknown type: ' . $typeA);
            return 0.0;
        }
        
        $features = [$this->extractFeatures($appartement)];
        
        if ($this->normalizer) {
            $this->normalizer->transform($features);
        }
        
        if (!$this->regressor) {
            throw new \Exception('Regressor not initialized');
        }
        
        $prediction = $this->regressor->predict($features[0]);
        
        return is_numeric($prediction) ? max(0, (float) $prediction) : 0.0; // Ensure non-negative
        
    } catch (\Exception $e) {
        error_log('Prediction error: ' . $e->getMessage());
        return 0.0;
    }
}

private function extractFeatures($appartement): array
{
    $typeMap = [
        'S+0' => 0,
        'S+1' => 1,
        'S+2' => 2,
        'S+3' => 3,
        'S+4' => 4,
        'S+5' => 5,
    ];
    
    $type = $typeMap[strtoupper($appartement->getTypeA())] ?? 0;
    
    return [
        (float) $appartement->getSuperficie(),
        (int) $appartement->isParking(),
        (int) $type,
    ];
}

public function saveModel(string $path): void 
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    
    file_put_contents(
        $path,
        serialize([
            'regressor' => $this->regressor,
            'normalizer' => $this->normalizer,
        ])
    );
}

public function loadModel(string $path): void
{
    $data = unserialize(file_get_contents($path));
    $this->regressor = $data['regressor'];
    $this->normalizer = $data['normalizer'];
}
}