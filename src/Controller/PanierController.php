<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Produit;
use App\Entity\Panier;
use App\Entity\ContenuPanier;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Contracts\Translation\TranslatorInterface;
class PanierController extends AbstractController
{

    #[Route('/info_panier', name: 'app_info_panier')]
    public function infoPanier(TranslatorInterface $translator): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            // Si l'utilisateur n'est pas connecté, rediriger vers la page de login
            $this->addFlash('error', $translator->trans('error.connexion_required'));
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
                'message' => $translator->trans('error.panier_vide'),
            ]);
        }
        
        // Vérifier l'état du panier
        if ($panierActif->isEtat()) {
            return $this->render('panier/info_panier.html.twig', [
                'found' => false,
                'message' => $translator->trans('error.panier_vide'),
            ]);
        }

        // Récuperer le prix Total des produits du panier
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

        // Afficher la page avec les détails du panier et le prix total
        return $this->render('panier/info_panier.html.twig', [
            'found' => true,
            'message' => 'Panier existant',
            'panier' => $panierActif,
            'produits' => $detailsProduits,
            'prixTotal' => $prixTotal,
        ]);
    }



    #[Route('/ajouter_panier/{id}', name: 'app_ajouter_panier')]
    public function ajouterAuPanier(Produit $produit, EntityManagerInterface $em, TranslatorInterface $translator)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {            
            $this->addFlash('error', $translator->trans('error.connexion_required'));
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
        $this->addFlash('success', $translator->trans('success.product_add'));
        return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
    }

    #[Route('/supprimer/{id}', name: 'app_supprimer_panier')]
    public function supprimer(int $id, EntityManagerInterface $em, TranslatorInterface $translator)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            $this->addFlash('error', $translator->trans('error.connexion_required'));
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
            $this->addFlash('error', $translator->trans('error.suppr'));
            return $this->redirectToRoute('app_info_panier');
        }

        // Récupérer l'entité ContenuPanier correspondant à l'ID du produit à supprimer
        $contenuPanier = $em->getRepository(ContenuPanier::class)->findOneBy([
            'produit' => $id,
            'panier' => $panierActif,
        ]);

        // Si l'article n'existe pas dans le panier, rediriger vers la page du panier
        if (!$contenuPanier) {
            $this->addFlash('error', $translator->trans('error.suppr'));
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
        $this->addFlash('success', $translator->trans('success.suppr'));
        return $this->redirectToRoute('app_info_panier');
    }    

    #[Route('/payer', name: 'app_payer_panier')]
    public function payerPanier(EntityManagerInterface $em, TranslatorInterface $translator){
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            $this->addFlash('error', $translator->trans('error.connexion_required'));
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
            $this->addFlash('error', $translator->trans('error.payement'));
            return $this->redirectToRoute('app_info_panier');
        }

        // Modifier l'état du panier à payé
        $panierActif->setEtat(true);
        $panierActif->setDateAchat(new \DateTime());
        $em->persist($panierActif);
        $em->flush();

        $this->addFlash('success', $translator->trans('success.payement'));
        return $this->render('panier/info_panier.html.twig', [
            'found' => false,
            'message' => 'Vous n\'avez pas d\'article dans votre panier.',
        ]);
    }

    #[Route('/utilisateur/commande/{id}', name: 'app_commande_show')]
    public function showCommande(int $id, EntityManagerInterface $em, TranslatorInterface $translator)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            $this->addFlash('error', $translator->trans('error.connexion_required'));
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();

        // Récupérer la commande spécifique de l'utilisateur
        $commande = $em->getRepository(Panier::class)->findOneBy([
            'utilisateur' => $utilisateur,
            'id' => $id
        ]);

        // Vérifier si la commande existe
        if (!$commande) {
            // Si la commande n'est pas trouvée, rediriger vers la liste des commandes
            $this->addFlash('error', $translator->trans('error.show_command'));
            return $this->redirectToRoute('app_utilisateur');
        }

        $contenus = $commande->getContenuPaniers();
        $prixTotal = 0;
        foreach ($contenus as $contenu) {
            $produit = $contenu->getProduit();
            $prixTotal += $produit->getPrix() * $contenu->getQuantite();
        }

        // Passer la commande à la vue pour affichage
        return $this->render('utilisateur/show.html.twig', [
            'commande' => $commande,
            'prixTotal' => $prixTotal,
        ]);
    }


}
