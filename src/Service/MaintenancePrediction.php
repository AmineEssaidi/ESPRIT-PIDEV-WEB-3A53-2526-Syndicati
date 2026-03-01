<?php
namespace App\Service;

use App\Entity\Residence\Appartement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MaintenancePrediction
{
    public function __construct(
        private HttpClientInterface $client,
        private string $mistralApiKey  // renamed from geminiApiKey
    ) {}

    public function predict(Appartement $apartment): string
    {
        $maintenance = $apartment->getMaintenance();
        $today = (new \DateTime())->format('d-m-Y');
        $minDate = (new \DateTime('+2 weeks'))->format('d-m-Y');

        $prompt = <<<PROMPT
        Tu es un expert en maintenance de bâtiments. Analyse cet appartement et fournis une prédiction de maintenance courte et concise.
        Aujourd'hui nous sommes le {$today}. La date de maintenance recommandée doit être APRÈS le {$minDate} (minimum 2 semaines à partir d'aujourd'hui).

        Données de l'appartement:
        - Type: {$apartment->getTypeA()}
        - Superficie: {$apartment->getSuperficie()} m²
        - Date de construction: {$apartment->getDateConstruction()->format('d-m-Y')}

        Fiche maintenance:
        - État général: {$maintenance?->getEtatApp()}
        - État plomberie: {$maintenance?->getEtatPlomberie()}
        - État électricité: {$maintenance?->getEtatElectricite()}
        - État chauffage: {$maintenance?->getEtatChauffage()}
        - Dernière maintenance: {$maintenance?->getDateDerniereMaintenance()->format('d-m-Y')}
        - Notes: {$maintenance?->getDescriptionMaint()}

        Réponds UNIQUEMENT dans ce format exact, sans astérisques, sans texte supplémentaire:
        Etat: [Faible / Modéré / Critique]
        Actions: [actions recommandées dans l'appartement lui même, et équipements à réparer, max 3 phrases, retourne à la ligne pour chaque action]
        Date de maintenance Recommendée: [YYYY-MM-DD]
        PROMPT;

        $response = $this->client->request(
            'POST',
            'https://api.mistral.ai/v1/chat/completions',
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->mistralApiKey,
                ],
                'json' => [
                    'model'       => 'mistral-small-latest',
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens'  => 600,
                    'temperature' => 0.4,
                ],
            ]
        );

        $data = $response->toArray();
        return $data['choices'][0]['message']['content']
            ?? 'Impossible de générer une recommandation.';
    }
}