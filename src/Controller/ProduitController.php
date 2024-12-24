<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;

use App\Form\ProduitAddType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;


use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
class ProduitController extends AbstractController
{
    #[Route('/index', name: 'app_produit')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $produits = $em->getRepository(Produit::class)->findAll();
        return $this->render('produit/index.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'app_produit_add')]
    public function addProduct(Request $request, EntityManagerInterface $em): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitAddType::class, $produit);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            print('form submitted');
            $imageFile = $form->get('photo')->getData();
 
            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
 
                try {
                    $imageFile->move(
                        $this->getParameter('upload_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error','Impossible d\'ajouter l\`image');
                    return $this->redirectToRoute('app_product');
                }
 
                $produit->setPhoto($newFilename);
            }
            $em->persist($produit);
            $em->flush();
            $this->addFlash('success','Produit Ajouté');
            return $this->redirectToRoute('app_produit');
        }
        print('k,o,o');
        return $this->render('produit/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/produit/{id}', name: 'app_produit_show')]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/produit/{id}/modifier', name: 'app_produit_edit')]
    public function editProduct(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProduitAddType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Produit modifié avec succès.');
            return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
        }

        return $this->render('produit/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

}
