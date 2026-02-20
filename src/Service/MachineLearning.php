<?php

namespace App\Service;

use Phpml\Regression\LeastSquares;
use Phpml\Preprocessing\Normalizer;

class MachineLearning
{
    private LeastSquares $regressor;
    private Normalizer $normalizer;
    private string $csvPath = 'public/tunisia-real-estate.csv';

    public function __construct()
    {
        $this->regressor = new LeastSquares();
        $this->normalizer = new Normalizer();
    }

    public function trainFromCsv(): void
    {
        if (!file_exists($this->csvPath)) {
            throw new \Exception("CSV file not found: {$this->csvPath}");
        }

        $features = [];
        $prices = [];
        $skippedCount = 0;

        $handle = fopen($this->csvPath, 'r');
        if ($handle === false) {
            throw new \Exception("Unable to open CSV file: {$this->csvPath}");
        }

        $header = fgetcsv($handle, 0, ",");
        if ($header === false) {
            fclose($handle);
            throw new \Exception("Invalid CSV file: unable to read header");
        }

        $header = array_map('trim', $header);
        $columnMap = array_flip($header);

        $requiredColumns = ['Nature', 'Type of Real Estate', 'Surface', 'Price'];
        foreach ($requiredColumns as $col) {
            if (!isset($columnMap[$col])) {
                fclose($handle);
                throw new \Exception("CSV missing required column: $col");
            }
        }

        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $row = array_map('trim', $row);

            $nature = $row[$columnMap['Nature']] ?? null;
            $typeRealEstate = $row[$columnMap['Type of Real Estate']] ?? null;
            $surface = $row[$columnMap['Surface']] ?? null;
            $price = $row[$columnMap['Price']] ?? null;

            if ($nature !== 'Rental') {
                $skippedCount++;
                continue;
            }

            $apartmentType = $this->parseApartmentType($typeRealEstate);
            if ($apartmentType === null) {
                $skippedCount++;
                continue;
            }

            if (empty($surface) || empty($price)) {
                $skippedCount++;
                continue;
            }

            $features[] = $this->extractFeatures((float) $surface, (int) $apartmentType);
            $prices[] = (float) $price;
        }

        fclose($handle);

        if (empty($features)) {
            throw new \Exception('No valid rental apartments found in CSV for training');
        }

        $this->normalizer->fit($features);
        $this->normalizer->transform($features);
        $this->regressor->train($features, $prices);
    }

    public function predict($appartement): float
    {
        try {
            $superficie = $appartement->getSuperficie();
            $typeA = $appartement->getTypeA();

            if (empty($superficie) || empty($typeA)) {
                return 0.0;
            }

            $typeValue = $this->getTypeValue($typeA);
            if ($typeValue === null) {
                return 0.0;
            }

            $features = [$this->extractFeatures((float) $superficie, (int) $typeValue)];

            if ($this->normalizer) {
                $this->normalizer->transform($features);
            }

            if (!$this->regressor) {
                throw new \Exception('Regressor not initialized');
            }

            $prediction = $this->regressor->predict($features[0]);
            return is_numeric($prediction) ? max(0, (float) $prediction) : 0.0;

        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function parseApartmentType(?string $typeString): ?int
    {
        if (empty($typeString)) {
            return null;
        }

        $typeString = strtolower(trim($typeString));

        $typeMap = [
            '1-room apartment' => 1,
            '2-room apartment' => 2,
            '3-room apartment' => 3,
            '4-room apartment' => 4,
            '5-room apartment' => 5,
            '6-room apartment' => 6,
        ];

        $excludedTypes = ['houses', 'surfaces', 'vacant', 'agricultural land', 'land', 'house', 'surface'];

        foreach ($excludedTypes as $excluded) {
            if (strpos($typeString, $excluded) !== false) {
                return null;
            }
        }

        return $typeMap[$typeString] ?? null;
    }

    private function getTypeValue(string $typeA): ?int
    {
        $typeMap = [
            'S+0' => 0,
            'S+1' => 1,
            'S+2' => 2,
            'S+3' => 3,
            'S+4' => 4,
            'S+5' => 5,
        ];

        return $typeMap[strtoupper($typeA)] ?? null;
    }

    private function extractFeatures(float $surface, int $apartmentType): array
    {
        $surfaceScaled = $surface * 1.5;
        $roomsScaled = (float) $apartmentType * 1.2;
        
        return [
            $surfaceScaled,
            $roomsScaled,
        ];
    }

    public function saveModel(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, serialize([
            'regressor' => $this->regressor,
            'normalizer' => $this->normalizer,
        ]));
    }

    public function loadModel(string $path): void
    {
        if (!file_exists($path)) {
            throw new \Exception("Model file not found: $path");
        }

        $data = unserialize(file_get_contents($path));
        $this->regressor = $data['regressor'];
        $this->normalizer = $data['normalizer'];
    }
}