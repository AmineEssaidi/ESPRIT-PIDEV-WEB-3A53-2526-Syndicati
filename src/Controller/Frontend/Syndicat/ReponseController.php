<?php

namespace App\Controller\Frontend\Syndicat;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\ReponsesRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Form\Backend\ReponsesType;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Reponses;
use App\Entity\Reclamations;
use App\Repository\ReclamationsRepository;


class ReponseController extends AbstractController
{


//     #[Route('/showsyndicat/reponse', name: 'frontend_syndicat_reponse_a_faire')]
// public function showreponse(ReponsesRepository $reponserep): Response
// {
//     $a = $reponserep->findAll();
//     return $this->render('frontend/syndicat/reponse/index.html.twig', [
//         'listreponse' => $a,
//     ]);
// }
    


    

#[Route('/showsyndicat/reponse/afaire', name: 'frontend_syndicat_reponse_a_faire')]
public function index(ReclamationsRepository $reclamationRepo): Response
{
    return $this->render('frontend/syndicat/reponse/index.html.twig', [
        'listreclamation' => $reclamationRepo->findAll(),
    ]);
}



// #[Route(
//     '/showsyndicat/reponse/show/{id}',
//     name: 'frontend_syndicat_reponse_show'
// )]
// public function showConversation(Reclamations $reclamation): Response
// {
//     return $this->render('frontend/syndicat/reponse/show.html.twig', [
//         'reclamation' => $reclamation,
//         'reponses' => $reclamation->getReponses(),
//     ]);
// }


#[Route(
    '/showsyndicat/reponse/show/{id}',
    name: 'frontend_syndicat_reponse_show'
)]
public function showConversation(
    int $id,
    ReclamationsRepository $reclamationRepo
): Response {
    $reclamation = $reclamationRepo->find($id);

    if (!$reclamation) {
        // reclamation deleted → go back to admin dashboard
        return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
    }

    return $this->render('frontend/syndicat/reponse/show.html.twig', [
        'reclamation' => $reclamation,
        'reponses' => $reclamation->getReponses(),
    ]);
}












    // #[Route('/showsyndicat/reponse/new', name: 'frontend_syndicat_reponse_new')]
    // public function createbook(Request $req, ManagerRegistry $m): Response
    // {
    //     $em = $m->getManager();
    //     $j = new Reponses;
    //     $form = $this->createForm(ReponsesType::class, $j);
    //     $form->handleRequest($req);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $em->persist($j);
    //         $em->flush();
    //         return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
    //     }
    //     return $this->render('frontend/syndicat/reponse/newreponse.html.twig', [
    //         'f' => $form->createView(),
    //     ]);
    // }




//     #[Route('/showsyndicat/reponse/new/{id}', name: 'frontend_syndicat_reponse_new')]
// public function create(
//     Reclamations $reclamation,
//     Request $request,
//     ManagerRegistry $doctrine
// ): Response {
//     $reponse = new Reponses();
//     $reponse->setReclamation($reclamation); // ✅ auto-link

//     $form = $this->createForm(ReponsesType::class, $reponse);
//     $form->handleRequest($request);

//     if ($form->isSubmitted() && $form->isValid()) {
//         $doctrine->getManager()->persist($reponse);
//         $doctrine->getManager()->flush();

//         return $this->redirectToRoute(
//             'frontend_syndicat_reponse_a_faire',
//             ['id' => $reclamation->getId()]
//         );
//     }

//     return $this->render('frontend/syndicat/reponse/newreponse.html.twig', [
//         'f' => $form->createView(),
//         'reclamation' => $reclamation,
//     ]);
// }



#[Route('/showsyndicat/reponse/new/{id}', name: 'frontend_syndicat_reponse_new')]
public function create(
    int $id,
    Request $request,
    ReclamationsRepository $reclamationRepo,
    ManagerRegistry $doctrine
): Response {
    $em = $doctrine->getManager();

    //  Manually fetch the reclamation
    $reclamation = $reclamationRepo->find($id);

    //  Reclamation no longer exists (deleted by user)
    if (!$reclamation) {
        return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
    }

    $reponse = new Reponses();
    $reponse->setReclamation($reclamation);

    $form = $this->createForm(ReponsesType::class, $reponse);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($reponse);
        $em->flush();

        // //  Back to admin dashboard
        return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
    }

    return $this->render('frontend/syndicat/reponse/newreponse.html.twig', [
        'f' => $form->createView(),
        'reclamation' => $reclamation,
    ]);
}











    // #[Route('/showsyndicat/reponse/delete/{id}', name: 'delete_reponse')]
    // public function deletebook($id,ReponsesRepository $reponserep,ManagerRegistry $m): Response
    // {
    //     $em = $m->getManager();
    //     $a = $reponserep->find($id);
    //     $em->remove($a);
    //     $em->flush();
    //     return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');

    // }

    // #[Route('/showsyndicat/reponse/delete/{id}', name: 'delete_reponse')]
    // public function deleteReponse(Reponses $reponse, ManagerRegistry $doctrine): Response
    // {
    //     $em = $doctrine->getManager();
    //     $em->remove($reponse); //  delete row
    //     $em->flush();

    //     return $this->redirectToRoute('frontend_syndicat_reponse_show');
    // }


//     #[Route('/showsyndicat/reponse/delete/{id}', name: 'delete_reponse')]
// public function deleteReponse(Reponses $reponse, ManagerRegistry $doctrine): Response
// {
//     $reclamationId = $reponse->getReclamation()->getId(); //  store before delete

//     $em = $doctrine->getManager();
//     $em->remove($reponse);
//     $em->flush();

//     return $this->redirectToRoute(
//         'frontend_syndicat_reponse_show',
//         ['id' => $reclamationId]
//     );
// }




// #[Route('/showsyndicat/reponse/delete/{id}', name: 'delete_reponse')]
// public function deleteReponse(int $id,ManagerRegistry $doctrine): Response {
//     $em = $doctrine->getManager();
//     $reponse = $em->getRepository(Reponses::class)->find($id);

//     // Response already deleted (cascade or concurrent action)
//     if (!$reponse) {
//         return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
//     }

//     //  Save reclamation ID BEFORE delete
//     $reclamationId = $reponse->getReclamation()->getId();

//     $em->remove($reponse);
//     $em->flush();

//     return $this->redirectToRoute(
//         'frontend_syndicat_reponse_show',
//         ['id' => $reclamationId]
//     );
// }





#[Route('/showsyndicat/reponse/delete/{id}', name: 'delete_reponse')]
public function deletebook(
    int $id,
    ReponsesRepository $reponserep,
    ManagerRegistry $m
): Response {
    $em = $m->getManager();
    $reponse = $reponserep->find($id);

    // // Response already deleted
    if (!$reponse) {
        return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
    }

    // // Save reclamation ID before delete
    $reclamationId = $reponse->getReclamation()->getId();

    $em->remove($reponse);
    $em->flush();

    return $this->redirectToRoute(
        'frontend_syndicat_reponse_show',
        ['id' => $reclamationId]
    );
}












//     #[Route('/showsyndicat/reponse/edit/{id}', name: 'frontend_syndicat_reponse_edit')]
// public function edit(
//     Reponses $reponse,
//     Request $request,
//     ManagerRegistry $doctrine
// ): Response {
//     $form = $this->createForm(ReponsesType::class, $reponse);
//     $form->handleRequest($request);

//     if ($form->isSubmitted() && $form->isValid()) {
//         $doctrine->getManager()->flush();

//         return $this->redirectToRoute(
//             'frontend_syndicat_reponse_show',
//             ['id' => $reponse->getReclamation()->getId()]
//         );
//     }

//     return $this->render('frontend/syndicat/reponse/edit.html.twig', [
//         'form' => $form->createView(),
//         'reponse' => $reponse,
//     ]);
// }




// #[Route('/showsyndicat/reponse/edit/{id}', name: 'frontend_syndicat_reponse_edit')]
// public function edit(int $id,Request $request,ManagerRegistry $doctrine): Response {
//     $em = $doctrine->getManager();

//     //  Manually fetch the response
//     $reponse = $em->getRepository(Reponses::class)->find($id);

//     //  Response no longer exists (deleted via cascade)
//     if (!$reponse) {
//         return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
//     }

//     $form = $this->createForm(ReponsesType::class, $reponse);
//     $form->handleRequest($request);

//     if ($form->isSubmitted() && $form->isValid()) {
//         $em->flush();

//         return $this->redirectToRoute(
//             'frontend_syndicat_reponse_show',
//             ['id' => $reponse->getReclamation()->getId()]
//         );
//     }

//     return $this->render('frontend/syndicat/reponse/edit.html.twig', [
//         'form' => $form->createView(),
//         'reponse' => $reponse,
//     ]);
// }





#[Route('/showsyndicat/reponse/edit/{id}', name: 'frontend_syndicat_reponse_edit')]
public function edit(int $id,Request $request,ManagerRegistry $doctrine): Response {
    $em = $doctrine->getManager();

    // Manually fetch the response
    $reponse = $em->getRepository(Reponses::class)->find($id);

    // Response no longer exists (deleted via cascade or concurrent action)
    if (!$reponse) {
        return $this->redirectToRoute('frontend_syndicat_reponse_a_faire');
    }

    $form = $this->createForm(ReponsesType::class, $reponse);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();

        //  Redirect back to the conversation (reclamation)
        return $this->redirectToRoute(
            'frontend_syndicat_reponse_show',
            ['id' => $reponse->getReclamation()->getId()]
        );
    }

    return $this->render('frontend/syndicat/reponse/edit.html.twig', [
        'form' => $form->createView(),
        'reponse' => $reponse,
    ]);
}




    

}
