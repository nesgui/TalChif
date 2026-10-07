<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Form\OrganisationType;
use App\Repository\OrganisationRepository;
use App\Repository\UserRepository;
use App\Service\Erreur\RapporteurErreurs;
use App\Service\Form\ErreursFormulaire;
use App\Service\Notification\MessagesFlash;
use App\Service\Tenant\ContexteTenant;
use App\Service\Tenant\ServiceOrganisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\Quota\ServiceQuotas;
use App\Service\Quota\QuotaDepasseException;

/**
 * Cycle de vie d'une organisation vu par ses membres : creation, bascule,
 * equipe.
 *
 * Complete le modele multi-tenant, qui supportait deja plusieurs membres et un
 * taux par organisation sans qu'aucun ecran ne le permette.
 */
#[Route('/organisateur/organisation')]
#[IsGranted('ROLE_USER')]
final class OrganisationController extends AbstractController
{
    public function __construct(
        private ServiceOrganisation $serviceOrganisation,
        private ContexteTenant $contexteTenant,
        private OrganisationRepository $organisationRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MessagesFlash $messagesFlash,
        private ErreursFormulaire $erreursFormulaire,
        private RapporteurErreurs $rapporteurErreurs,
        private ServiceQuotas $serviceQuotas,
    ) {
    }

    /**
     * Liste les organisations de l'utilisateur et designe la courante.
     */
    #[Route('', name: 'organisation.index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $utilisateur */
        $utilisateur = $this->getUser();

        return $this->render('organisation/index.html.twig', [
            'appartenances' => $this->contexteTenant->appartenances($utilisateur),
            'courante' => $this->contexteTenant->resoudrePour($utilisateur),
        ]);
    }

    #[Route('/creer', name: 'organisation.create', methods: ['GET', 'POST'])]
    public function create(Request $request, SluggerInterface $slugger): Response
    {
        /** @var User $utilisateur */
        $utilisateur = $this->getUser();

        $organisation = new Organisation();
        $formulaire = $this->createForm(OrganisationType::class, $organisation);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            try {
                $organisation->setSlug(
                    $this->organisationRepository->genererSlugUnique((string) $slugger->slug((string) $organisation->getNom()))
                );
                $organisation->setActif(true);
                $this->entityManager->persist($organisation);

                // Le createur en est proprietaire : une organisation sans
                // proprietaire serait ingerable.
                $this->serviceOrganisation->rattacher(
                    $organisation,
                    $utilisateur,
                    MembreOrganisation::ROLE_PROPRIETAIRE
                );
                $this->entityManager->flush();

                $this->contexteTenant->basculerVers($utilisateur, $organisation);
                $this->messagesFlash->succes('Organisation « ' . $organisation->getNom() . ' » creee.');

                return $this->redirectToRoute('organisation.index');
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'organisation_create']);
            }
        } elseif ($formulaire->isSubmitted()) {
            $this->erreursFormulaire->publierEnFlash($formulaire);
        }

        return $this->render('organisation/create.html.twig', [
            'form' => $formulaire->createView(),
        ]);
    }

    /**
     * Bascule l'organisation de travail. L'appartenance est revalidee par
     * ContexteTenant : un identifiant devine ne donne aucun acces.
     */
    #[Route('/basculer', name: 'organisation.basculer', methods: ['POST'])]
    public function basculer(Request $request): Response
    {
        /** @var User $utilisateur */
        $utilisateur = $this->getUser();
        $retour = $request->request->get('retour') === 'dashboard'
            ? 'organisateur.dashboard'
            : 'organisation.index';

        if (!$this->isCsrfTokenValid('organisation_basculer', (string) $request->request->get('_token'))) {
            $this->messagesFlash->erreur('Token de securite invalide. Veuillez reessayer.');

            return $this->redirectToRoute($retour);
        }

        $organisation = $this->organisationRepository->find($request->request->getInt('organisation'));
        if (!$organisation instanceof Organisation) {
            $this->messagesFlash->erreur('Organisation introuvable.');

            return $this->redirectToRoute($retour);
        }

        try {
            $this->contexteTenant->basculerVers($utilisateur, $organisation);
            $this->messagesFlash->succes('Vous travaillez desormais sur « ' . $organisation->getNom() . ' ».');
        } catch (\RuntimeException $e) {
            // Appartenance absente ou organisation desactivee : aucun acces accorde.
            $this->messagesFlash->erreur($e->getMessage());
        }

        return $this->redirectToRoute($retour);
    }

    /**
     * Equipe de l'organisation courante.
     */
    #[Route('/equipe', name: 'organisation.equipe', methods: ['GET'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function equipe(): Response
    {
        /** @var User $utilisateur */
        $utilisateur = $this->getUser();

        $organisation = $this->contexteTenant->resoudrePour($utilisateur);
        if ($organisation === null) {
            $this->messagesFlash->avertissement('Creez d abord une organisation.');

            return $this->redirectToRoute('organisation.create');
        }

        return $this->render('organisation/equipe.html.twig', [
            'organisation' => $organisation,
            'membres' => $organisation->getMembres(),
            'roles' => MembreOrganisation::ROLES,
            'estProprietaire' => $utilisateur->roleDansOrganisation($organisation) === MembreOrganisation::ROLE_PROPRIETAIRE,
        ]);
    }

    #[Route('/equipe/inviter', name: 'organisation.equipe.inviter', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function inviter(Request $request): Response
    {
        $organisation = $this->organisationGerable($request);
        if (!$organisation instanceof Organisation) {
            return $organisation;
        }

        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $role = (string) $request->request->get('role', MembreOrganisation::ROLE_GESTIONNAIRE);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messagesFlash->erreur('Adresse email invalide.');

            return $this->redirectToRoute('organisation.equipe');
        }

        if (!\in_array($role, MembreOrganisation::ROLES, true)) {
            $this->messagesFlash->erreur('Role inconnu.');

            return $this->redirectToRoute('organisation.equipe');
        }

        $invite = $this->userRepository->findByEmail($email);
        if ($invite === null) {
            // Aucune creation de compte ici : un membre doit exister et avoir
            // choisi son mot de passe lui-meme.
            $this->messagesFlash->erreur('Aucun compte TalChif avec cette adresse. La personne doit d abord s inscrire.');

            return $this->redirectToRoute('organisation.equipe');
        }

        try {
            // Quota bloquant, sauf pour un membre deja rattache dont on ne fait
            // que changer le role.
            if ($invite->roleDansOrganisation($organisation) === null) {
                $this->serviceQuotas->verifierAjoutMembre($organisation);
            }

            $this->serviceOrganisation->rattacher($organisation, $invite, $role);
            $this->entityManager->flush();
            $this->messagesFlash->succes($invite->getEmail() . ' rejoint l equipe en tant que ' . mb_strtolower($role) . '.');
        } catch (QuotaDepasseException $e) {
            $this->messagesFlash->erreur($e->getMessage());
        } catch (\Throwable $e) {
            $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'organisation_inviter']);
        }

        return $this->redirectToRoute('organisation.equipe');
    }

    #[Route('/equipe/role/{id}', name: 'organisation.equipe.role', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function changerRole(MembreOrganisation $membre, Request $request): Response
    {
        $organisation = $this->organisationGerable($request);
        if (!$organisation instanceof Organisation) {
            return $organisation;
        }

        if ($membre->getOrganisation()?->getId() !== $organisation->getId()) {
            throw $this->createAccessDeniedException('Ce membre appartient a une autre organisation.');
        }

        $role = (string) $request->request->get('role', '');
        if (!\in_array($role, MembreOrganisation::ROLES, true)) {
            $this->messagesFlash->erreur('Role inconnu.');

            return $this->redirectToRoute('organisation.equipe');
        }

        try {
            $membre->setRole($role);
            $this->entityManager->flush();
            $this->messagesFlash->succes('Role mis a jour.');
        } catch (\Throwable $e) {
            $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'organisation_role']);
        }

        return $this->redirectToRoute('organisation.equipe');
    }

    #[Route('/equipe/retirer/{id}', name: 'organisation.equipe.retirer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function retirer(MembreOrganisation $membre, Request $request): Response
    {
        $organisation = $this->organisationGerable($request);
        if (!$organisation instanceof Organisation) {
            return $organisation;
        }

        if ($membre->getOrganisation()?->getId() !== $organisation->getId()) {
            throw $this->createAccessDeniedException('Ce membre appartient a une autre organisation.');
        }

        $utilisateurRetire = $membre->getUtilisateur();
        if (!$utilisateurRetire instanceof User) {
            return $this->redirectToRoute('organisation.equipe');
        }

        try {
            $this->serviceOrganisation->detacher($organisation, $utilisateurRetire);
            $this->messagesFlash->succes($utilisateurRetire->getEmail() . ' a ete retire de l equipe.');
        } catch (\RuntimeException $e) {
            $this->messagesFlash->erreur($e->getMessage());
        }

        return $this->redirectToRoute('organisation.equipe');
    }

    /**
     * Organisation courante, a condition que l'utilisateur en soit proprietaire
     * et que le jeton CSRF soit valide.
     *
     * Renvoie une redirection quand la condition n'est pas remplie.
     */
    private function organisationGerable(Request $request): Organisation|Response
    {
        /** @var User $utilisateur */
        $utilisateur = $this->getUser();

        if (!$this->isCsrfTokenValid('organisation_equipe', (string) $request->request->get('_token'))) {
            $this->messagesFlash->erreur('Token de securite invalide. Veuillez reessayer.');

            return $this->redirectToRoute('organisation.equipe');
        }

        $organisation = $this->contexteTenant->resoudrePour($utilisateur);
        if ($organisation === null) {
            return $this->redirectToRoute('organisation.create');
        }

        if ($utilisateur->roleDansOrganisation($organisation) !== MembreOrganisation::ROLE_PROPRIETAIRE) {
            throw $this->createAccessDeniedException('Seul un proprietaire gere l equipe.');
        }

        return $organisation;
    }
}
