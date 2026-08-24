<?php

namespace App\Controller;

use DateTime;
use App\Entity\Todo;
use App\Enum\Statut;
use App\Enum\Importance;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TodoController extends AbstractController
{
    #[Route('/todo', name: 'todo_liste', methods: ['GET'])]
    public function liste(TodoRepository $todoRepository, Request $request)
    {
        $filtre = $request->query->get('filtre');
        $nbTermine = $todoRepository->count(['statut' => Statut::TERMINE, 'dateSuppression' => null]);
        $nbTotal = $todoRepository->count(['dateSuppression' => null]);

        if ($filtre === 'termine') {
            $todos = $todoRepository->findBy(['statut' => Statut::TERMINE, 'dateSuppression' => null], ['ordre' => 'ASC']);
        } elseif ($filtre === 'a_faire') {
            $todos = $todoRepository->findBy(['statut' => Statut::A_FAIRE, 'dateSuppression' => null], ['ordre' => 'ASC']);
        } elseif ($filtre === 'en_cours') {
            $todos = $todoRepository->findBy(['statut' => Statut::EN_COURS, 'dateSuppression' => null], ['ordre' => 'ASC']);
        } else {
            $todos = $todoRepository->findBy(['dateSuppression' => null], ['ordre' => 'ASC']);
        }

        if (date("H") >= 6 && date("H") <= 12){
            $messageAccueil = "Bonjour";
        } else if (date("H") > 12 && date("H") <= 18){
            $messageAccueil = "Bon après-midi";
        } else {
            $messageAccueil = "Bonsoir";
        }

        $todosAFaire = $todoRepository->findBy(['statut' => Statut::A_FAIRE, 'dateSuppression' => null], ['ordre' => 'ASC']);

        $poidsImportance = [
            'urgente' => 1,
            'importante' => 2,
            'normale' => 3,
        ];

        usort($todosAFaire, function ($a, $b) use ($poidsImportance) {
            $poidsA = $poidsImportance[$a->getImportance()->value];
            $poidsB = $poidsImportance[$b->getImportance()->value];

            if ($poidsA === $poidsB) {
                $dateA = $a->getDateFin();
                $dateB = $b->getDateFin();

                if ($dateA !== null && $dateB === null) {
                    return -1;
                }

                if ($dateA === null && $dateB !== null) {
                    return 1;
                }

                return $dateA <=> $dateB;
            }

            return $poidsA <=> $poidsB;
        });

        return $this->render('todo/liste.html.twig', [
            'todos' => $todos,
            'filtre' => $filtre,
            'tacheNow' => $todosAFaire[0] ?? null,
            'nbTermine' => $nbTermine,
            'nbTotal' => $nbTotal,
            'messageAccueil' => $messageAccueil,
        ]);
    }

    #[Route('/todo/nouveau', name: 'todo_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $entityManager, TodoRepository $todoRepository)
    {
        $titre = $request->request->get('titre');
        $description = $request->request->get('description');
        $dateFin = $request->request->get('dateFin');
        $heures = $request->request->get('heures');
        $minutes = $request->request->get('minutes');
        $importance = $request->request->get('importance');

        if ($titre === '') {
            $this->addFlash('erreur', 'Le titre est obligatoire');
            return $this->redirectToRoute('todo_liste');
        }

        $todo = new Todo();
        $todo->setTitre($titre);
        $todo->setStatut(Statut::A_FAIRE);
        $todo->setImportance(Importance::from($importance));

        if (!empty($description)) {
            $todo->setDescription($description);
        }

        if (!empty($dateFin)) {
            $todo->setDateFin(DateTime::createFromFormat('Y-m-d', $dateFin));
        }

        if (!empty($heures) || !empty($minutes)) {
            $duree = intval($heures) * 60 + intval($minutes);
            $todo->setDuree($duree);
        }

        $nombreTodos = $todoRepository->count([]);
        $todo->setOrdre($nombreTodos);
        
        $entityManager->persist($todo);
        $entityManager->flush();

        return $this->redirectToRoute('todo_liste');
    }

    #[Route('/todo/modifier/{id}', name: 'todo_modifier', methods: ['POST'])]
    public function modifier(int $id, Request $request, TodoRepository $todoRepository, EntityManagerInterface $entityManager)
    {
        $todo = $todoRepository->find($id);

        $titre = $request->request->get('newTitre');
        $description = $request->request->get('description');
        $dateFin = $request->request->get('dateFin');
        $heures = $request->request->get('heures');
        $minutes = $request->request->get('minutes');
        $importance = $request->request->get('importance');

        if ($titre === '') {
            $this->addFlash('erreur', 'Le titre est obligatoire');
            return $this->redirectToRoute('todo_liste');
        }

        if (!empty($description)) {
            $todo->setDescription($description);
        } else {
            $todo->setDescription(null);
        }

        if (!empty($dateFin)) {
            $todo->setDateFin(DateTime::createFromFormat('Y-m-d', $dateFin));
        } else {
            $todo->setDateFin(null);
        }

        if (!empty($heures) || !empty($minutes)) {
            $duree = intval($heures) * 60 + intval($minutes);
            $todo->setDuree($duree);
        } else {
            $todo->setDuree(null);
        }

        $todo->setTitre($titre);
        $todo->setImportance(Importance::from($importance));
        $entityManager->flush();

        return $this->redirectToRoute('todo_liste');
    }

    #[Route('/todo/supprimer/{id}', name: 'todo_supprimer', methods: ['POST'])]
    public function supprimer(int $id, Request $request, TodoRepository $todoRepository, EntityManagerInterface $entityManager)
    {
        $todo = $todoRepository->find($id);

        $todo->setDateSuppression(new DateTime());
        $entityManager->flush();

        return $this->redirectToRoute('todo_liste');
    }

    #[Route('/todo/supprimerTermines', name: 'todo_supprimer_termines', methods: ['POST'])]
    public function supprimerTermines(TodoRepository $todoRepository, EntityManagerInterface $entityManager)
    {
        $todos = $todoRepository->findBy(['statut' => Statut::TERMINE]);

        foreach ($todos as $todo) {
            $entityManager->remove($todo);
        }

        $entityManager->flush();

        return $this->redirectToRoute('todo_liste');
    }

    #[Route('/todo/suppressionGroupee', name:'suppression_groupee', methods: ['POST'])]
    public function suppressionGroupee(Request $request, TodoRepository $todoRepository, EntityManagerInterface $entityManager)
    {
        $idsASupprimer = $request->request->all('todosASupprimer');

        if (!empty($idsASupprimer)) {
            foreach( $idsASupprimer as $id) {
                $todo = $todoRepository->find($id);
                $entityManager->remove($todo);
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('todo_liste');
    }

    #[Route('/todo/statut/{id}/{statut}', name: 'todo_statut', methods: ['POST'])]
    public function changerStatut(int $id, string $statut, TodoRepository $todoRepository, EntityManagerInterface $entityManager)
    {
        $todo = $todoRepository->find($id);

        $todo->setStatut(Statut::from($statut));

        $entityManager->flush();

        return $this->redirectToRoute('todo_liste');
    }

    #[Route('/statistiques', name:'statistiques', methods: ['GET'])]
    public function statistiques(TodoRepository $todoRepository)
    {
        $nbAFaire = $todoRepository->count(['statut' => Statut::A_FAIRE, 'dateSuppression' => null]);
        $nbEnCours = $todoRepository->count(['statut' => Statut::EN_COURS, 'dateSuppression' => null]);
        $nbTermine = $todoRepository->count(['statut' => Statut::TERMINE, 'dateSuppression' => null]);
        $nbTotal = $todoRepository->count(['dateSuppression' => null]);

        if ($nbTotal == 0) {
            $pourcentageTermine = 0;
            $pourcentageEnCours = 0;
            $pourcentageAFaire = 0;
        } else {
            $pourcentageAFaire = $nbAFaire / $nbTotal * 100;
            $pourcentageEnCours = $nbEnCours / $nbTotal * 100;
            $pourcentageTermine = $nbTermine / $nbTotal * 100;
        }

        return $this->render('todo/statistiques.html.twig', [
            'nbAFaire' => $nbAFaire,
            'nbEnCours' => $nbEnCours,
            'nbTermine' => $nbTermine,
            'nbTotal' => $nbTotal,
            'pourcentageAFaire' => $pourcentageAFaire,
            'pourcentageEnCours' => $pourcentageEnCours,
            'pourcentageTermine' => $pourcentageTermine,
        ]);
    }

    #[Route('/todo/reordonner', name: 'todo_reordonner', methods: ['POST'])]
    public function reordonner(Request $request, TodoRepository $todoRepository, EntityManagerInterface $entityManager)
    {
        $idsOrdonnes = $request->request->all('ordre');

        foreach ($idsOrdonnes as $index => $id) {
            $todo = $todoRepository->find($id);
            $todo->setOrdre($index);
        }

        $entityManager->flush();

        return new Response('OK');
    }
}