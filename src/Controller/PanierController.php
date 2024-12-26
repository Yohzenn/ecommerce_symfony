<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Produit;
use App\Entity\Panier;
use App\Entity\ContenuPanier;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
class PanierController extends AbstractController
{

    #[Route('/produit/info_panier', name: 'app_info_panier')]
    public function infoPanier(EntityManagerInterface $em): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            // Si l'utilisateur n'est pas connecté, rediriger vers la page de login
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();

        $panierActif = null;
        foreach ($utilisateur->getPaniers() as $panier) {
            if (!$panier->isEtat()) { // Vérifie si le panier est actif
                $panierActif = $panier;
                break;
            }
        }
        
        // Vérifier si un panier est associé à cet utilisateur
        if (!$panierActif) {
            return $this->render('panier/info_panier.html.twig', [
                'found' => false,
                'message' => 'Vous n\'avez pas d\'article dans votre panier.',
            ]);
        }
        
        // Vérifier l'état du panier
        if ($panierActif->isEtat()) {
            return $this->render('panier/info_panier.html.twig', [
                'found' => false,
                'message' => 'Vous n\'avez pas d\'article dans votre panier.',
            ]);
        }

        $contenus = $panierActif->getContenuPaniers();
        $detailsProduits = [];
        $prixTotal = 0;
        foreach ($contenus as $contenu) {
            $produit = $contenu->getProduit();
            $detailsProduits[] = [
                'id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'prix' => $produit->getPrix(),
                'quantite' => $contenu->getQuantite(),
            ];
            $prixTotal += $produit->getPrix() * $contenu->getQuantite();
        }

        return $this->render('panier/info_panier.html.twig', [
            'found' => true,
            'message' => 'Panier existant',
            'panier' => $panierActif,
            'produits' => $detailsProduits,
            'prixTotal' => $prixTotal,
        ]);
    }



    #[Route('/produit/{id}/ajouter-au-panier', name: 'app_ajouter_panier')]
    public function ajouterAuPanier(Produit $produit, EntityManagerInterface $em)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();

        // Rechercher un panier actif (non payé) dans la collection des paniers de l'utilisateur
        $panierActif = null;
        foreach ($utilisateur->getPaniers() as $panier) {
            if (!$panier->isEtat()) { // Vérifie si le panier est actif
                $panierActif = $panier;
                break;
            }
        }

        if (!$panierActif) {
            // Si aucun panier actif n'existe, créer un nouveau panier
            $panierActif = new Panier();
            $panierActif->setUtilisateur($utilisateur);
            $panierActif->setEtat(false); // Nouveau panier non payé
            $panierActif->setDateAchat(new \DateTime());

            $utilisateur->addPanier($panierActif); // Lier le panier à l'utilisateur
            $em->persist($panierActif);
        }

        // Vérifier si le produit est déjà dans le panier actif
        $contenuPanier = $em->getRepository(ContenuPanier::class)
            ->findOneBy(['produit' => $produit, 'panier' => $panierActif]);

        if (!$contenuPanier) {
            // Ajouter le produit au panier si inexistant
            $contenuPanier = new ContenuPanier();
            $contenuPanier->setProduit($produit);
            $contenuPanier->setPanier($panierActif);
            $contenuPanier->setQuantite(1); // Quantité initiale
            $contenuPanier->setDateAjout(new \DateTime());

            $em->persist($contenuPanier);
        } else {
            // Incrémenter la quantité si le produit existe déjà dans le panier
            $contenuPanier->setQuantite($contenuPanier->getQuantite() + 1);
        }

        // Sauvegarder toutes les modifications
        $em->flush();

        // Rediriger vers la page de la fiche produit
        return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
    }

    #[Route('/produit/supprimer/{id}', name: 'app_supprimer_panier')]
    public function supprimer(int $id, EntityManagerInterface $em)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();

        // Récupérer le panier actif de l'utilisateur
        $panierActif = null;
            foreach ($utilisateur->getPaniers() as $panier) {
                if (!$panier->isEtat()) { // Vérifie si le panier est actif
                    $panierActif = $panier;
                    break;
                }
            }

        // Si aucun panier n'est trouvé, rediriger vers la page d'information du panier
        if (!$panier) {
            return $this->redirectToRoute('app_info_panier');
        }

        // Récupérer l'entité ContenuPanier correspondant à l'ID du produit à supprimer
        $contenuPanier = $em->getRepository(ContenuPanier::class)->findOneBy([
            'produit' => $id,
            'panier' => $panierActif,
        ]);

        // Si l'article n'existe pas dans le panier, rediriger vers la page du panier
        if (!$contenuPanier) {
            return $this->redirectToRoute('app_info_panier');
        }

        // Supprimer le produit du panier
        $em->remove($contenuPanier);
        $em->flush();
        if ($panier->getContenuPaniers()->isEmpty()) {
            // Si le panier est vide, le supprimer
            $em->remove($panier);
            $em->flush();
        }

        // Rediriger vers la page du panier
        return $this->redirectToRoute('app_info_panier');
    }    

    #[Route('/produit/payer', name: 'app_payer_panier')]
    public function payerPanier(EntityManagerInterface $em){
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();

        // Récupérer le panier actif de l'utilisateur
        $panierActif = null;
        foreach ($utilisateur->getPaniers() as $panier) {
            if (!$panier->isEtat()) { // Vérifie si le panier est actif
                $panierActif = $panier;
                break;
            }
        }

        // Si aucun panier n'est trouvé, rediriger vers la page d'information du panier
        if (!$panierActif) {
            return $this->redirectToRoute('app_info_panier');
        }

        // Modifier l'état du panier à payé
        $panierActif->setEtat(true);
        $em->persist($panierActif);
        $em->flush();

        // Rediriger vers la page de confirmation de paiement
        return $this->render('app_produit');
    }
}
