<?php

namespace App\Controller\Comment;

use App\Invalidator\EventInvalidator;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\BrowserCache;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\TBNController as Controller;
use App\Entity\Agenda;
use App\Form\Type\CommentType;
use App\Entity\Comment;
use App\Repository\CommentRepository;

class CommentController extends Controller
{
    public function detailsAction(Comment $comment)
    {
        return $this->render('Comment/details.html.twig', [
            'comment'     => $comment,
            'nb_reponses' => $this->getNbReponses($comment),
        ]);
    }

    /**
     * @Route("/{id}/{page}", name="tbn_comment_list", requirements={"id": "\d+", "page": "\d+"})
     * @BrowserCache(false)
     *
     * @param Agenda $soiree
     * @param $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @Cache(expires="tomorrow")
     */
    public function listAction(Agenda $soiree, $page = 1)
    {
        $offset  = 10;
        $comment = new Comment();
        $form    = $this->getCreateForm($comment, $soiree);

        $response = $this->render('Comment/list.html.twig', [
            'nb_comments' => $this->getNbComments($soiree),
            'comments'    => $this->getCommentaires($soiree, $page, $offset),
            'soiree'      => $soiree,
            'page'        => $page,
            'offset'      => $offset,
            'form'        => $form->createView(),
        ]);

        $tomorrow = $this->getSecondsUntilTomorrow();
        $response->setSharedMaxAge($tomorrow);

        return $response;
    }

    /**
     * @Route("/{id}/nouveau", name="tbn_comment_new", requirements={"id": "\d+"})
     *
     * @param Request $request
     * @param Agenda $soiree
     *
     * @param EventInvalidator $eventInvalidator
     * @return Response
     */
    public function newAction(Request $request, Agenda $soiree, EventInvalidator $eventInvalidator)
    {
        $comment = new Comment();
        $form    = $this->getCreateForm($comment, $soiree);

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'post'    => $this->renderView('Comment/error.html.twig'),
            ]);
        }

        $comment->setUser($user);
        $comment->setAgenda($soiree);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $eventInvalidator->addEvent($soiree);
            $em->persist($comment);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'comment' => $this->renderView('Comment/details.html.twig', [
                    'comment'              => $comment,
                    'success_confirmation' => true,
                    'nb_reponses'          => 0,
                ]),
                'header' => $this->renderView('Comment/header.html.twig', [
                    'nb_comments' => $this->getNbComments($soiree),
                ]),
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'post'    => $this->renderView('Comment/post.html.twig', [
                'form' => $form->createView(),
            ]),
        ]);
    }

    /**
     * @return CommentRepository
     */
    protected function getCommentRepo()
    {
        return $this->getDoctrine()->getRepository(Comment::class);
    }

    protected function getCommentaires(Agenda $soiree, $page = 1, $limit = 10)
    {
        return $this->getCommentRepo()->findAllByAgenda($soiree, $page, $limit);
    }

    protected function getReponses(Comment $comment, $page = 1, $limit = 10)
    {
        return $this->getCommentRepo()->findAllReponses($comment, $page, $limit);
    }

    protected function getNbComments(Agenda $soiree)
    {
        return $this->getCommentRepo()->findNBCommentaires($soiree);
    }

    protected function getNbReponses(Comment $comment)
    {
        return $this->getCommentRepo()->findNBReponses($comment);
    }

    protected function getCreateForm(Comment $comment, Agenda $soiree)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('tbn_comment_new', ['id' => $soiree->getId()]),
            'method' => 'POST',
        ])
            ->add('poster', SubmitType::class, [
                'label' => 'Poster',
                'attr'  => [
                    'class'             => 'btn btn-primary btn-submit btn-raised',
                    'data-loading-text' => 'En cours...',
                ],
            ]);
    }
}
