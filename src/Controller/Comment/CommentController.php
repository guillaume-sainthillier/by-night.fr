<?php

namespace App\Controller\Comment;

use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\Comment;
use App\Form\Type\CommentType;
use App\Repository\CommentRepository;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends BaseController
{
    /**
     * @Route("/form/{id}", name="app_comment_form", requirements={"id": "\d+"})
     */
    public function form(Event $event)
    {
        $comment = new Comment();

        $form = null;
        if ($this->getUser()) {
            $form = $this->getCreateForm($comment, $event)->createView();
        }

        return $this->render('Comment/list_and_form.html.twig', [
            'nb_comments' => $this->getNbComments($event),
            'comments' => $this->getCommentaires($event, 1, 10),
            'event' => $event,
            'page' => 1,
            'offset' => 10,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}/{page}", name="app_comment_list", requirements={"id": "\d+", "page": "\d+"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function listAction(Event $event, $page = 1)
    {
        $offset = 10;
        $comment = new Comment();
        $form = $this->getCreateForm($comment, $event);

        return $this->render('Comment/list.html.twig', [
            'nb_comments' => $this->getNbComments($event),
            'comments' => $this->getCommentaires($event, $page, $offset),
            'event' => $event,
            'page' => $page,
            'offset' => $offset,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/nouveau", name="app_comment_new", requirements={"id": "\d+"})
     */
    public function newAction(Request $request, Event $event)
    {
        $comment = new Comment();
        $form = $this->getCreateForm($comment, $event);

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'post' => $this->renderView('Comment/error.html.twig'),
            ]);
        }

        $comment->setUser($user);
        $comment->setEvent($event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'comment' => $this->renderView('Comment/details.html.twig', [
                    'comment' => $comment,
                    'success_confirmation' => true,
                    'nb_reponses' => 0,
                ]),
                'header' => $this->renderView('Comment/header.html.twig', [
                    'nb_comments' => $this->getNbComments($event),
                ]),
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'post' => $this->renderView('Comment/post.html.twig', [
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

    protected function getCommentaires(Event $event, $page = 1, $limit = 10)
    {
        return $this->getCommentRepo()->findAllByEvent($event, $page, $limit);
    }

    protected function getReponses(Comment $comment, $page = 1, $limit = 10)
    {
        return $this->getCommentRepo()->findAllReponses($comment, $page, $limit);
    }

    protected function getNbComments(Event $event)
    {
        return $this->getCommentRepo()->findNBCommentaires($event);
    }

    protected function getNbReponses(Comment $comment)
    {
        return $this->getCommentRepo()->findNBReponses($comment);
    }

    protected function getCreateForm(Comment $comment, Event $event)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
            'method' => 'POST',
        ])
            ->add('poster', SubmitType::class, [
                'label' => 'Poster',
                'attr' => [
                    'class' => 'btn btn-primary btn-submit btn-raised',
                    'data-loading-text' => 'En cours...',
                ],
            ]);
    }
}
