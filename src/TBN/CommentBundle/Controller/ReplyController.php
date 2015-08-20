<?php

namespace TBN\CommentBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

use TBN\CommentBundle\Form\Type\CommentType;
use TBN\CommentBundle\Entity\Comment;
use TBN\UserBundle\Entity\User;
use TBN\AgendaBundle\Entity\Agenda;


class ReplyController extends Controller
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

    protected function getCommentaires(Agenda $soiree, $page, $limit = 10)
    {
        return $this->getCommentRepo()->findAllByAgenda($soiree, $page, $limit);
    }

    protected function getReponses(Comment $comment, $page, $limit = 10)
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
        return $this->render("TBNCommentBundle:Reply:details.html.twig",[
           "comment" => $comment,
            "nb_reponses" => $this->getNbReponses($comment)
        ]);
    }

    public function listAction(Comment $comment, $page)
    {
        $limit = 5;
        return $this->render('TBNCommentBundle:Reply:list.html.twig', [
            'comments' => $this->getReponses($comment, $page, $limit),
            "main_comment" => $comment,
            "nb_comments" => $this->getNbReponses($comment),
            "page" => $page,
            "offset" => $limit
        ]);
    }

    public function newAction(Request $request, Comment $comment)
    {
        $reponse = new Comment();
        $form = $this->getCreateForm($reponse, $comment);

        if($request->getMethod() == "POST")
        {
            $user = $this->getUser();

            if(! $user)
            {
                $this->get('session')->getFlashBag()->add(
                    'warning',
                    "Vous devez vous connecter pour répondre à cet utilisateur"
                );

                return new RedirectResponse($this->generateUrl("fos_user_security_login"));
            }
            $reponse->setUser($user);
            $reponse->setAgenda($comment->getAgenda());

            $form->bind($request);
            if ($form->isValid())
            {
                $reponse->setParent($comment);
                $comment->addReponse($reponse);
                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();

                return new JsonResponse([
                    "success" => true,
                    "comment" => $this->container->get("templating")->render("TBNCommentBundle:Reply:details.html.twig",[
                        "comment" => $reponse,
                        "success_confirmation" => true,
                    ]),
                    "nb_reponses" => $this->getNbReponses($comment)
                ]);
            }else
            {
                return new JsonResponse([
                    "success" => false,
                    "post" => $this->container->get("templating")->render("TBNCommentBundle:Reply:post.html.twig", [
                        "comment" => $comment,
                        "form" => $form->createView()
                    ])
                ]);
            }
        }
        return $this->render('TBNCommentBundle:Reply:post.html.twig', [
            'comment' => $comment,
            'form' => $form->createView()
        ]);
    }


    protected function getCreateForm(Comment $reponse, Comment $comment)
    {
        return $this->createForm(new CommentType(), $reponse,[
            'action' => $this->generateUrl('tbn_comment_reponse_new', ["id" => $comment->getId()]),
            'method' => 'POST'
            ])
            ->add("poster","submit", [
                "label" => "Répondre",
                "attr" => [
                    "class" => "btn btn-primary btn-submit btn-raised",
                    "data-loading-text" => "En cours..."
                ]
        ]);
    }
}
