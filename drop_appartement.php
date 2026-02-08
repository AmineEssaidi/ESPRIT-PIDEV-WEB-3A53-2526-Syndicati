<?php
require __DIR__ . '/vendor/autoload.php';
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $em->getConnection();
$conn->executeQuery('DROP TABLE IF EXISTS appartement');
echo "Table appartement dropped.\n";
