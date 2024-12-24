<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Produit;
use App\Entity\Panier;
use App\Entity\ContenuPanier;
use Doctrine\ORM\EntityManagerInterface;
class PanierController extends AbstractController
{
#[Route('/produit/{id}/ajouter-au-panier', name: 'app_ajouter_panier')]
    public function ajouterAuPanier(Produit $produit, EntityManagerInterface $entityManager)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            // Si l'utilisateur n'est pas connecté, rediriger vers la page de login
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();

        // Vérifier si l'utilisateur a déjà un panier
        $panier = $utilisateur->getPanier();
        if (!$panier) {
            // Si l'utilisateur n'a pas de panier, en créer un
            $panier = new Panier();
            $panier->setUtilisateur($utilisateur);
            $panier->setEtat(false);
            $panier->setDateAchat(new \DateTime());
            $entityManager->persist($panier);
            $entityManager->flush();
        }

        // Vérifier si le produit est déjà dans le panier
        $contenuPanier = $entityManager->getRepository(ContenuPanier::class)
            ->findOneBy(['produit' => $produit, 'panier' => $panier]);

        if (!$contenuPanier) {
            // Si le produit n'est pas déjà dans le panier, on l'ajoute
            $contenuPanier = new ContenuPanier();
            $contenuPanier->setProduit($produit);
            $contenuPanier->setPanier($panier);
            $contenuPanier->setQuantite(1); // Ajout de 1 produit par défaut
            $contenuPanier->setDateAjout(new \DateTime());

            $entityManager->persist($contenuPanier);
            $entityManager->flush();
        }

        // Rediriger vers la page de la fiche produit
        return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
    }
}
