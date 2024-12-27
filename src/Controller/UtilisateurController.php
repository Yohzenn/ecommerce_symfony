<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\PhpUnit\TextUI\Command;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UtilisateurController extends AbstractController
{

    #[Route('/utilisateur', name: 'app_utilisateur')]
    public function index(EntityManagerInterface $em)
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            return $this->redirectToRoute('app_login');
        }
        
        // Récupérer les commandes de l'utilisateur
        $commandes = $em->getRepository(Panier::class)->findBy(['utilisateur' => $utilisateur]);

        // Calculer le prix total pour chaque commande
        $prixTotal = [];
        foreach ($commandes as $commande) {
            $prixTotal[] = $this->calculerPrixTotal($commande); // Méthode pour calculer le prix total
        }

        return $this->render('utilisateur/index.html.twig', [
            'utilisateur' => $utilisateur,
            'commandes' => $commandes,
            'prixTotal' => $prixTotal,
        ]);
    }
    
    #[Route('/utilisateur/{id}/modifier', name: 'app_utilisateur_edit')]
    public function editProduct(Utilisateur $utilisateur, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(RegistrationFormType::class, $utilisateur);
        $form->handleRequest($request);
        $em->flush();
        if($form->isSubmitted() && $form->isValid()){
            $this->addFlash('success', $translator->trans('success.user_modified'));
            return $this->redirectToRoute('app_utilisateur');
        }
        return $this->render('utilisateur/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function calculerPrixTotal(Panier $commande)
    {
        $total = 0;
        foreach ($commande->getContenuPaniers() as $contenu) {
            $total += $contenu->getProduit()->getPrix() * $contenu->getQuantite();
        }
        return $total;
    }

    
    #[Route('/super', name: 'app_super')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function super(EntityManagerInterface $em)
    {
        // Récupérer tous les utilisateurs inscrits aujourd'hui, triés du plus récent au plus ancien
        $dateDuJour = new \DateTime('today'); // Date de début de la journée
        $utilisateurs = $em->getRepository(Utilisateur::class)->createQueryBuilder('u')
            ->where('u.dateInscription >= :dateDuJour') // Utilisateurs inscrits aujourd'hui
            ->setParameter('dateDuJour', $dateDuJour)
            ->orderBy('u.dateInscription', 'DESC') // Tri du plus récent au plus ancien
            ->getQuery()
            ->getResult();

        // Récupérer tous les paniers non achetés (état = false)
        $paniersNonAchetes = $em->getRepository(Panier::class)->findBy(['etat' => false]);
        $utilisateursNonPayes=[];
        foreach ($paniersNonAchetes as $panier) {
            $utilisateur = $panier->getUtilisateur();
            $utilisateursNonPayes[] = $utilisateur;
        }

        // Passer les utilisateurs et les paniers non achetés à la vue
        return $this->render('utilisateur/super.html.twig', [
            'utilisateurs' => $utilisateurs,
            'paniersNonAchetes' => $paniersNonAchetes,
            'utilisateursNonPayes' => $utilisateursNonPayes,
        ]);

    }
}
