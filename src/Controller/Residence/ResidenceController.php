<?php

namespace App\Controller\Residence;

use App\Service\SmsGenerator;
use App\Repository\Residence\ResidenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;

#[Route('/residence')]
class ResidenceController extends AbstractController
{
    #[Route('/', name: 'app_residence_index', methods: ['GET', 'POST'])]
    public function index(ResidenceRepository $residenceRepository): Response
    {
        return $this->render('frontend/residence/index.html.twig', [
    'smsSent' => false,
    'residences' => $residenceRepository->findAll(),
]);
    }

#[Route('/sendSms', name: 'send_sms', methods: ['POST'])]
public function sendSms(SmsGenerator $smsGenerator, ResidenceRepository $residenceRepository): Response
{
    $name = $this->getUser() ? $this->getUser()->getFirstName() : '';
    $text = "Bonjour";
    $number_test = $_ENV['twilio_to_number'];
    
    $smsGenerator->sendSms($number_test, $name, $text);
    
    return $this->render('frontend/residence/index.html.twig', [
        'smsSent' => true,
        'residences' => $residenceRepository->findAll()
    ]);
}
}
