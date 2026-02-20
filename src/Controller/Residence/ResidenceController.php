<?php

namespace App\Controller\Residence;

use App\Entity\Residence\Residence;
use App\Form\Residence\ResidenceType;
use App\Service\SmsGenerator;
use App\Repository\Residence\ResidenceRepository;
use App\Repository\User\UserRepository;
use App\Entity\Residence\Appartement;
use App\Form\Residence\AppartementType;
use App\Repository\Residence\AppartementRepository;
use App\Service\PageStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use App\Entity\User\User;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;

use App\Service\MachineLearning;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


#[Route('/residence')]
class ResidenceController extends AbstractController
{
    #[Route('/', name: 'app_residence_index', methods: ['GET', 'POST'])]
    public function index(Request $request, ResidenceRepository $residenceRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $query = $residenceRepository->createQueryBuilder('r')
            ->orderBy('r.dateAjout', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3 // 3 residences per page
        );

        return $this->render('frontend/residence/index.html.twig', [
    'smsSent' => false,
    'residences' => $pagination,
        ]);
    }

    // --- Appartement Methods ---

  #[Route('/appartement/new', name: 'app_appartement_new', methods: ['POST'])]
    public function newAppartement(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FormFactoryInterface $formFactory, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $appartement = new Appartement();
        $form = $formFactory->createNamed('appartement_add', AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageA')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('appartements_directory'), $newFilename);
                    $appartement->setImageA($newFilename);
                } catch (\Exception $e) {
                }
            }

            $appartement->setAppartementInfo([
                'bloc' => $form->get('bloc')->getData(),
                'floor' => $form->get('floor')->getData(),
                'number' => $form->get('number')->getData(),
                'parking' => $form->get('parking')->getData(),
                'disponible' => $form->get('disponible')->getData(),
            ]);

            $entityManager->persist($appartement);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Apartment registered successfully.',
                'appartement' => [
                    'id' => $appartement->getIdApp(),
                    'type' => $appartement->getTypeA(),
                    'residence' => $appartement->getResidence() ? $appartement->getResidence()->getNomR() : 'N/A',
                    'resId' => $appartement->getResidence() ? $appartement->getResidence()->getIdResidence() : '',
                    'owner' => $appartement->getUser() ? $appartement->getUser()->getEmailUser() : 'N/A',
                    'ownerId' => $appartement->getUser() ? $appartement->getUser()->getIdUser() : '',
                    'parking' => $appartement->isParking() ? 'Yes' : 'No',
                    'isParking' => (bool) $appartement->isParking(),
                    'status' => $appartement->isDisponible() ? 'Available' : 'Occupied',
                    'isAvailable' => (bool) $appartement->isDisponible(),
                    'image' => $appartement->getImageA() ? '/uploads/images/' . $appartement->getImageA() : '/frontend/images/property-placeholder.jpg',
                    'bloc' => $form->get('bloc')->getData(),
                    'floor' => $form->get('floor')->getData(),
                    'number' => $form->get('number')->getData(),
                    'deleteToken' => $csrfTokenManager->getToken('appartement_delete')->getValue()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }
    #[Route('/appartement/{idApp}/edit', name: 'app_appartement_edit', methods: ['POST'])]
    public function editAppartement(Request $request, int $idApp, AppartementRepository $repository, EntityManagerInterface $entityManager, SluggerInterface $slugger, FormFactoryInterface $formFactory, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $appartement = $repository->find($idApp);
        if (!$appartement) {
            return new JsonResponse(['success' => false, 'message' => 'Apartment not found.'], 404);
        }

        $form = $formFactory->createNamed('appartement_edit', AppartementType::class, $appartement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageA')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('appartements_directory'), $newFilename);
                    $appartement->setImageA($newFilename);
                } catch (\Exception $e) {
                }
            }

            $jsonInfo = [
                'bloc' => $form->get('bloc') ? $form->get('bloc')->getData() : null,
                'floor' => $form->get('floor') ? $form->get('floor')->getData() : null,
                'number' => $form->get('number') ? $form->get('number')->getData() : null,
                'parking' => $form->get('parking')->getData(),
                'disponible' => $form->get('disponible')->getData(),
            ];
            $appartement->setAppartementInfo($jsonInfo);
            $appartement->setParking($form->get('parking')->getData());
            $appartement->setDisponible($form->get('disponible')->getData());

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Apartment updated successfully.',
                'appartement' => [
                    'id' => $appartement->getIdApp(),
                    'type' => $appartement->getTypeA(),
                    'residence' => $appartement->getResidence() ? $appartement->getResidence()->getNomR() : 'N/A',
                    'resId' => $appartement->getResidence() ? $appartement->getResidence()->getIdResidence() : '',
                    'owner' => $appartement->getUser() ? $appartement->getUser()->getEmailUser() : 'N/A',
                    'ownerId' => $appartement->getUser() ? $appartement->getUser()->getIdUser() : '',
                    'parking' => $appartement->isParking() ? 'Yes' : 'No',
                    'isParking' => (bool) $appartement->isParking(),
                    'status' => $appartement->isDisponible() ? 'Available' : 'Occupied',
                    'isAvailable' => (bool) $appartement->isDisponible(),
                    'image' => $appartement->getImageA() ? '/uploads/images/' . $appartement->getImageA() : '/frontend/images/property-placeholder.jpg',
                    'bloc' => $jsonInfo['bloc'],
                    'floor' => $jsonInfo['floor'],
                    'number' => $jsonInfo['number'],
                    'deleteToken' => $csrfTokenManager->getToken('appartement_delete')->getValue()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/appartement/{idApp}/delete', name: 'app_appartement_delete', methods: ['POST'])]
    public function deleteAppartement(Request $request, Appartement $appartement, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->isCsrfTokenValid('appartement_delete', $request->request->get('_token'))) {
            $entityManager->remove($appartement);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => 'Apartment deleted successfully.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }

    #[Route('/{id}/details', name: 'app_residence_details', methods: ['GET'])]
    public function details(int $id, ResidenceRepository $residenceRepository, Request $request): Response
    {
        $residence = $residenceRepository->find($id);
        if (!$residence) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Residence not found.'], 404);
            }
            throw $this->createNotFoundException('Residence not found.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('frontend/residence/show_modal.html.twig', [
                'residence' => $residence,
            ]);
        }

        return $this->render('frontend/residence/show.html.twig', [
            'residence' => $residence,
]);
    }

        #[Route('/{id}', name: 'residence_apartments_frame')]
        public function AfficherAppartements($id, ResidenceRepository $residenceRepository): Response
        {
            $residence = $residenceRepository->find($id);
            
            return $this->render('frontend/residence/appartements.html.twig', [
                'appartements' => $residence->getAppartements(),
                'residence' => $residence,
            ]);
        }

#[Route('/{id}/sendSms', name: 'send_sms', methods: ['GET', 'POST'])]
public function sendSms(SmsGenerator $smsGenerator, Request $request, UserRepository $userRep, ResidenceRepository $residenceRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
{
    $session = $request->getSession();
    $userId = (int) $session->get('user')['id'];
    $user = $userRep->find($userId);
    $name = $user->getFirstName();
    $text =$user->getEmailUser();
    $number_test = $_ENV['twilio_to_number'];
    
    $smsGenerator->sendSms($number_test, $name, $text);

            $query = $residenceRepository->createQueryBuilder('r')
            ->orderBy('r.dateAjout', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3 // 3 residences per page
        );

    
    return $this->render('frontend/residence/index.html.twig', [
        'smsSent' => true,
        'residences' => $pagination,
    ]);
}


    #[Route('/pdf/{id}', 'pdf_residence')]
    public function GenererPDFResidence($id, Request $request, GotenbergPdfInterface $gotenbergPdf, ResidenceRepository $residenceRepository): Response
    {
        $residence = $residenceRepository->find($id);
        return $gotenbergPdf->html()->content('frontend/residence/pdf_residence.html.twig', [
            'appartements' => $residence->getAppartements(),
           'residence' => $residence,
       ])
        ->generate()
        ->stream()
    ;
    }

}