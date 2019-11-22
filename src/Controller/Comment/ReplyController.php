<?php

namespace App\Controller\Comment;

use App\Entity\Comment;
use App\Entity\Event;
use App\Form\Type\CommentType;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReplyController extends AbstractController
{
    /**
     * @Route("/{id}/reponses/{page}", name="app_comment_reponse_list", requirements={"id": "\d+", "page": "\d+"})
     *
     * @param $page
     *
     * @return Response
     */
    public function listAction(Comment $comment, $page = 1)
    {
        $limit = 5;

        return $this->render('Comment/Reply/list.html.twig', [
            'comments' => $this->getReponses($comment, $page, $limit),
            'main_comment' => $comment,
            'nb_comments' => $this->getNbReponses($comment),
            'page' => $page,
            'offset' => $limit,
        ]);
    }

    protected function getReponses(Comment $comment, $page, $limit = 10)
    {
        return $this->getCommentRepo()->findAllReponses($comment, $page, $limit);
    }

    /**
     * @return CommentRepository
     */
    protected function getCommentRepo()
    {
        return $this->getDoctrine()->getRepository(Comment::class);
    }

    protected function getNbReponses(Comment $comment)
    {
        return $this->getCommentRepo()->findNBReponses($comment);
    }

    /**
     * @Route("/{id}/repondre", name="app_comment_reponse_new", requirements={"id": "\d+"})
     *
     * @return JsonResponse|RedirectResponse|Response
     */
    public function newAction(Request $request, Comment $comment)
    {
        $reponse = new Comment();
        $form = $this->getCreateForm($reponse, $comment);

        if ('POST' === $request->getMethod()) {
            $user = $this->getUser();

            if (!$user) {
                $this->addFlash(
                    'warning',
                    'Vous devez vous connecter pour répondre à cet utilisateur'
                );

                return new RedirectResponse($this->generateUrl('fos_user_security_login'));
            }
            $reponse->setUser($user);
            $reponse->setEvent($comment->getEvent());

            $form->handleRequest($request);
            if ($form->isValid()) {
                $reponse->setParent($comment);
                $comment->addReponse($reponse);
                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();

                return new JsonResponse([
                    'success' => true,
                    'comment' => $this->renderView('Comment/Reply/details.html.twig', [
                        'comment' => $reponse,
                        'success_confirmation' => true,
                    ]),
                    'nb_reponses' => $this->getNbReponses($comment),
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'post' => $this->renderView('Comment/Reply/post.html.twig', [
                    'comment' => $comment,
                    'form' => $form->createView(),
                ]),
            ]);
        }

        return $this->render('Comment/Reply/post.html.twig', [
            'comment' => $comment,
            'form' => $form->createView(),
        ]);
    }

    protected function getCreateForm(Comment $reponse, Comment $comment)
    {
        return $this->createForm(CommentType::class, $reponse, [
            'action' => $this->generateUrl('app_comment_reponse_new', ['id' => $comment->getId()]),
            'method' => 'POST',
        ]);
    }

    public function detailsAction(Comment $comment)
    {
        return $this->render('Comment/Reply/details.html.twig', [
            'comment' => $comment,
            'nb_reponses' => $this->getNbReponses($comment),
        ]);
    }

    protected function getCommentaires(Event $event, $page, $limit = 10)
    {
        return $this->getCommentRepo()->findAllByEvent($event, $page, $limit);
    }

    protected function getNbComments(Event $event)
    {
        return $this->getCommentRepo()->findNBCommentaires($event);
    }
}
