<?php

namespace App\Controller\Residence;

use App\Repository\Residence\ResidenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/residence')]
class ResidenceController extends AbstractController
{
    #[Route('/', name: 'app_residence_index', methods: ['GET'])]
    public function index(ResidenceRepository $residenceRepository): Response
    {
        return $this->render('frontend/residence/index.html.twig', [
            'residences' => $residenceRepository->findAll(),
        ]);
    }
}
