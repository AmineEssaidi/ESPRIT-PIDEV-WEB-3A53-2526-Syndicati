<?php
namespace App\Controller\Frontend\Evenement;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EvenementController extends AbstractController
{
    #[Route('/evenement', name: 'frontend_evenement')]
    public function index(): Response
    {
        return $this->render('frontend/evenement/index.html.twig');
    }
}