<?php
namespace App\Controller\Frontend\Syndicat;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SyndicatController extends AbstractController
{
    #[Route('/syndicat', name: 'frontend_syndicat')]
    public function index(): Response
    {
        return $this->render('frontend/syndicat/index.html.twig');
    }
}