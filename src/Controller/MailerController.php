<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\Residence\AppartementRepository;
use App\Service\Residence\ResidenceNotificationService;

class MailerController extends AbstractController
{
    #[Route('/email', name: 'envoyer_email_residence')]
    public function envoyerEmail(
        Request $request,
        AppartementRepository $appartementRepository,
        ResidenceNotificationService $notificationService
    ): Response {
        $idApp = $request->request->get('id_app');
        $appartement = $idApp ? $appartementRepository->find($idApp) : null;
        $isAjax = $request->isXmlHttpRequest() || $request->query->get('ajax');

        if (!$appartement) {
            if ($isAjax) {
                return $this->json(['status' => 'error', 'message' => 'Appartement non trouvé.'], 404);
            }
            $this->addFlash('error', 'Appartement non trouvé.');
            return $this->redirectToRoute('app_residence_index');
        }

        try {
            $notificationService->notifyApartmentContact($appartement, $request->request->all());

            if ($isAjax) {
                return $this->json(['status' => 'success', 'message' => 'Votre message a été envoyé avec succès!']);
            }

            return $this->render('frontend/main-home.html.twig', [
                'email_envoyé' => true,
            ]);
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->json(['status' => 'error', 'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage()], 500);
            }
            $this->addFlash('error', 'Une erreur est survenue.');
            return $this->redirectToRoute('app_residence_index');
        }
    }
}