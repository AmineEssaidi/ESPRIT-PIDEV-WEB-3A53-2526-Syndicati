<?php
use App\Kernel;
use App\Service\SyndicatiAgent;
use App\Service\PlaywrightService;
use App\Service\WebSearchService;
use App\Service\InternalKnowledgeService;
use App\Service\AgentMemoryService;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

require __DIR__ . '/../vendor/autoload.php';

// Create HttpClient directly (not from container since services are private)
$httpClient = HttpClient::create();

// We still need the kernel for SmartRouteMappingService
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->bootEnv(__DIR__ . '/../.env');
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$routeService = $container->get(\App\Service\SmartRouteMappingService::class);
$projectDir = $container->getParameter('kernel.project_dir');

// Create services
$playwright = new PlaywrightService($httpClient);
$webSearch = new WebSearchService($httpClient);
$knowledge = new InternalKnowledgeService($routeService, $projectDir);
$memory = new AgentMemoryService();

// Create agent
$agent = new SyndicatiAgent(
    $httpClient,
    $routeService,
    $playwright,
    $webSearch,
    $knowledge,
    $memory
);

$result = $agent->executeGoal('go to profile', ['currentUrl' => '/', 'pageTitle' => 'Home']);
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
