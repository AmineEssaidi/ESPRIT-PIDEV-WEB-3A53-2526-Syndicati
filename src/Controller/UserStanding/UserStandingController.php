<?php
namespace App\Controller\UserStanding;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user-standing')]
class UserStandingController extends AbstractController
{
    #[Route('/', name: 'app_user_standing_index')]
    public function index(): Response
    {
        return $this->render('user_standing/index.html.twig', [
            'controller_name' => 'UserStandingController',
        ]);
    }
}
