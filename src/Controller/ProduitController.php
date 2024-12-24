<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Produit;
use App\Entity\ContenuPanier;
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

    public function removeImage(string $imageName): void
    {
        $imagePath = $this->getParameter('upload_directory') . '/' . $imageName;

        if (file_exists($imagePath)) {
            unlink($imagePath); // Suppression du fichier
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/produit/{id}/modifier', name: 'app_produit_edit')]
    public function editProduct(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProduitAddType::class, $produit);
        $form->handleRequest($request);

        // Sauvegarde de l'image existante
        $currentImage = $produit->getPhoto();

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le fichier image téléchargé
            $imageFile = $form->get('photo')->getData();

            if ($imageFile) {
                // Supprimer l'ancienne image si une nouvelle image est téléchargée
                if ($currentImage) {
                    $this->removeImage($currentImage);  // Suppression de l'ancienne image
                }

                // Gérer la nouvelle image (enregistrer le fichier sur le serveur et mettre à jour la base de données)
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('upload_directory'),
                        $newFilename
                    );
                    $produit->setPhoto($newFilename);  // Mise à jour du nom de l'image dans la base de données
                } catch (FileException $e) {
                    $this->addFlash('error', 'Impossible d\'ajouter l\'image');
                    return $this->redirectToRoute('app_produit');
                }
            }

            $em->flush();
            $this->addFlash('success', 'Produit modifié avec succès.');
            return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
        }

        return $this->render('produit/edit.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
        ]);
    }


    #[Route('/produit/{id}/delete', name: 'app_produit_delete')] 
    public function delete(Request $request, EntityManagerInterface $em, Produit $produit = null)
    {
        if ($produit === null) {
            $this->addFlash('error', 'Product not found.');
            return $this->redirectToRoute('app_produit');
        }
        
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), $request->request->get('csrf_token'))) {
            
            // Vérifier s'il y a des liens avec des paniers dans ContenuPanier
            $contenuPaniers = $em->getRepository(ContenuPanier::class)->findBy(['produit' => $produit]);

            if (count($contenuPaniers) > 0) {
                // Si des liens existent, on peut les supprimer ou les dissocier
                foreach ($contenuPaniers as $contenuPanier) {
                    $em->remove($contenuPanier);
                }
                $em->flush();
                $this->addFlash('warning', 'Le produit était dans un panier et a été dissocié.');
            }

            // Supprimer le produit
            $em->remove($produit);
            $em->flush();

            $this->addFlash('success', 'Product has been deleted successfully.');
        } else {
            // CSRF token invalide
            $this->addFlash('error', 'Invalid CSRF token. The product was not deleted.');
        }

        return $this->redirectToRoute('app_produit');
    }

}
