<?php
namespace App\Controller\UserRelationship;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user-relationship')]
class UserRelationshipController extends AbstractController
{
    #[Route('/', name: 'app_user_relationship_index')]
    public function index(): Response
    {
        return $this->render('user_relationship/index.html.twig', [
            'controller_name' => 'UserRelationshipController',
        ]);
    }
}
