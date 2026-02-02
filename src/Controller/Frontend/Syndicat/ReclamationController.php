<?php

namespace App\Controller\Frontend\Syndicat;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\ReclamationsRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Form\Backend\ReclamType;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Reclamations;
use App\Entity\Reponses;



class ReclamationController extends AbstractController
{

    #[Route('/showsyndicat/reclamation', name: 'frontend_syndicat_reclamation')]
    public function showreclamation(ReclamationsRepository $reclamrep): Response
    {
        $a = $reclamrep->findAll();   
        return $this->render('frontend/syndicat/reclamation/index.html.twig', [
        'listreclamation' => $a,
        ]);
    }


      #[Route('/showsyndicat/reclamation/new', name: 'frontend_syndicat_reclamation_new')]
    public function createreclamation(Request $req,ManagerRegistry $m): Response
    {
        $em = $m->getManager();
        $j = new Reclamations;
        $form = $this->createForm(ReclamType::class,$j);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($j);
            $em->flush();
            return $this->redirectToRoute('frontend_syndicat_reclamation');
        }
        return $this->render('frontend/syndicat/reclamation/newreclamation.html.twig', [
            'f' => $form->createView(),
        ]);
    }



    #[Route('/showsyndicat/reclamation/delete/{id}', name: 'delete_reclamation')]
    public function deletebook($id,ReclamationsRepository $reclamrep,ManagerRegistry $m): Response
    {
        $em = $m->getManager();
        $a = $reclamrep->find($id);
        $em->remove($a);
        $em->flush();
        return $this->redirectToRoute('frontend_syndicat_reclamation');

    }



    #[Route('/showsyndicat/reclamation/edit/{id}', name: 'frontend_syndicat_reclamation_edit')]
public function edit(
    Reclamations $reclamation,
    Request $request,
    ManagerRegistry $doctrine
): Response {
    $form = $this->createForm(ReclamType::class, $reclamation);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $doctrine->getManager()->flush();

        return $this->redirectToRoute('frontend_syndicat_reclamation');
    }

    return $this->render('frontend/syndicat/reclamation/edit.html.twig', [
        'form' => $form->createView(),
        'reclamation' => $reclamation,
    ]);
}




//  #[Route('/showanimalby/{id}', name: 'show_animal_by')]
//     public function showanimalby($id,AnimalRepository $auhthorrep): Response
//     {
//         $a = $auhthorrep->find($id);
        

//         return $this->render('animal/animalbyid.html.twig', [
//             'listauthor' => $a,
//         ]);
//     }

    #[Route('/showsyndicat/reclamation/{id}', name: 'frontend_syndicat_reclamation_show')]
public function show(Reclamations $reclamation): Response
{
    return $this->render('frontend/syndicat/reclamation/show.html.twig', [
        'reclamation' => $reclamation,
    ]);
}



    



    







}
