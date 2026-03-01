<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:auto-translate',
    description: 'Automatically translates missing keys using local LibreTranslate Docker container',
)]
class AutoTranslateCommand extends Command
{
    private string $translationsDir;
    private HttpClientInterface $httpClient;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        HttpClientInterface $httpClient
    ) {
        parent::__construct();
        $this->translationsDir = $projectDir . '/translations';
        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('LibreTranslate AI Automation');

        $baseLocale = 'fr';
        $targetLocales = ['en', 'ar', 'es', 'de', 'pt', 'tr'];
        $domain = 'messages';

        if (!is_dir($this->translationsDir)) {
            mkdir($this->translationsDir, 0777, true);
        }

        $baseFile = sprintf('%s/%s.%s.yaml', $this->translationsDir, $domain, $baseLocale);
        if (!file_exists($baseFile)) {
            $io->error("Base translation file $baseFile not found. Please create it first.");
            return Command::FAILURE;
        }

        $baseTranslations = Yaml::parseFile($baseFile) ?? [];

        foreach ($targetLocales as $locale) {
            $targetFile = sprintf('%s/%s.%s.yaml', $this->translationsDir, $domain, $locale);
            $targetTranslations = file_exists($targetFile) ? (Yaml::parseFile($targetFile) ?? []) : [];

            $missingKeysContext = $this->findMissingKeys($baseTranslations, $targetTranslations);
            if (empty($missingKeysContext)) {
                $io->success("Locale [$locale] is already fully translated.");
                continue;
            }

            $io->info("Translating " . count($missingKeysContext) . " missing keys for [$locale] via LibreTranslate API (localhost:5000)...");

            foreach ($missingKeysContext as $keyPath => $originalText) {
                if (!is_string($originalText)) {
                    continue;
                }

                try {
                    $translatedText = $this->translateText($originalText, $baseLocale, $locale);
                    $this->setNestedValue($targetTranslations, explode('.', $keyPath), $translatedText);
                    $io->text(" ✓ [$locale] '$originalText' -> '$translatedText'");
                } catch (\Exception $e) {
                    $io->warning("Failed to translate '$originalText' (Is LibreTranslate running on port 5000?): " . $e->getMessage());
                }
            }

            // Save updated YAML
            $yaml = Yaml::dump($targetTranslations, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            file_put_contents($targetFile, $yaml);
            $io->success("Saved translations for [$locale].");
        }

        return Command::SUCCESS;
    }

    private function findMissingKeys(array $base, array $target, string $prefix = ''): array
    {
        $missing = [];
        foreach ($base as $key => $value) {
            $currentPath = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $subTarget = $target[$key] ?? [];
                $missing = array_merge($missing, $this->findMissingKeys($value, is_array($subTarget) ? $subTarget : [], $currentPath));
            } elseif (!isset($target[$key])) {
                $missing[$currentPath] = $value;
            }
        }
        return $missing;
    }

    private function setNestedValue(array &$array, array $keys, $value): void
    {
        $current = &$array;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    private function translateText(string $text, string $source, string $target): string
    {
        $response = $this->httpClient->request('POST', 'http://localhost:5000/translate', [
            'json' => [
                'q' => $text,
                'source' => $source === 'fr' ? 'fr' : 'en',
                'target' => $target,
                'format' => 'text'
            ],
            'timeout' => 10,
        ]);

        $data = $response->toArray();
        return $data['translatedText'] ?? $text;
    }
}
