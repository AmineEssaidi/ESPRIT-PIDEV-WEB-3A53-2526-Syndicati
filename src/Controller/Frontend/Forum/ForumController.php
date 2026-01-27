<?php
namespace App\Controller\Frontend\Forum;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForumController extends AbstractController
{
    #[Route('/forum', name: 'frontend_forum')]
    public function index(): Response
    {
        return $this->render('frontend/forum/index.html.twig');
    }
}