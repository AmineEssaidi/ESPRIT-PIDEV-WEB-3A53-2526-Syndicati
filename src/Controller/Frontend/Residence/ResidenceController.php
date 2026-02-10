<?php
namespace App\Controller\Frontend\Residence;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ResidenceController extends AbstractController
{
    #[Route('/residence', name: 'frontend_residence')]
    public function index(): Response
    {
        return $this->render('frontend/residence/index.html.twig');
    }
}