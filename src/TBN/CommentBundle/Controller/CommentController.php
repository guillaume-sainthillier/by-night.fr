<?php

namespace TBN\CommentBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\CommentBundle\Form\Type\CommentType;
use TBN\CommentBundle\Entity\Comment;
use TBN\UserBundle\Entity\User;

class CommentController extends Controller
{
    /**
     *
     * @return TBN\CommentBundle\Repository\CommentRepository
     */
    protected function getCommentRepo()
    {
        $repo = $this->getDoctrine()->getRepository("TBNCommentBundle:Comment");
        return $repo;
    }

    protected function getCommentaires(Agenda $soiree, $page =1, $limit = 10)
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

    public function detailsAction(Comment $comment)
    {
        return $this->render("TBNCommentBundle:Comment:details.html.twig",[
           "comment" => $comment,
            "nb_reponses" => $this->getNbReponses($comment)
        ]);
    }
    
    public function newAction(Request $request, Agenda $soiree)
    {
        $comment = new Comment();
        $form = $this->getCreateForm($comment, $soiree);

        $tokenStorage = $this->container->get('security.token_storage');
        $user = $tokenStorage->getToken()->getUser();

        if(! $user)
        {
            return new JsonResponse([
                "success" => false,
                "post" => $this->container->get("templating")->render("TBNCommentBundle:Comment:error.html.twig")
            ]);
        }

        $comment->setUser($user);
        $comment->setAgenda($soiree);
        $form->bind($request);
        if ($form->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();

            return new JsonResponse([
                "success" => true,
                "comment" => $this->container->get("templating")->render("TBNCommentBundle:Comment:details.html.twig",[
                    "comment" => $comment,
                    "success_confirmation" => true,
                    "nb_reponses" => 0

                ]),
                "header" => $this->container->get("templating")->render("TBNCommentBundle:Comment:header.html.twig",[
                    "nb_comments" => $this->getNbComments($soiree)
                ])
            ]);
        }

        return new JsonResponse([
            "success" => false,
            "post" => $this->container->get("templating")->render("TBNCommentBundle:Comment:post.html.twig", [
                "form" => $form->createView()
            ])
        ]);
    }


    protected function getCreateForm(Comment $comment, Agenda $soiree)
    {
        return $this->createForm(new CommentType(), $comment,[
            'action' => $this->generateUrl('tbn_comment_new', ["id" => $soiree->getId()]),
            'method' => 'POST'
            ])
            ->add("poster","submit", [
                "label" => "Poster",
                "attr" => [
                    "class" => "btn btn-primary btn-submit btn-raised",
                    "data-loading-text" => "En cours..."
                ]
        ]);
    }

    public function listAction(Agenda $soiree, $page)
    {
        $offset = 10;
        $comment = new Comment();
        $form = $this->getCreateForm($comment, $soiree);

        return $this->render('TBNCommentBundle:Comment:list.html.twig', [
            'nb_comments' => $this->getNbComments($soiree),
            'comments' => $this->getCommentaires($soiree, $page, $offset),
            "soiree" => $soiree,
            "page" => $page,
            "offset" => $offset,
            'form' => $form->createView()
        ]);
    }
}
