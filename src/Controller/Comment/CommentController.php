<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Comment;

use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use App\Entity\Comment;
use App\Entity\Event;
use App\Form\Type\CommentType;
use App\Repository\CommentRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends BaseController
{
    const COMMENTS_PER_PAGE = 10;

    /**
     * @Route("/form/{id}", name="app_comment_form", requirements={"id": "\d+"})
     */
    public function form(Event $event, CommentRepository $commentRepository, int $page = 1)
    {
        $comment = new Comment();

        $form = null;
        if ($this->getUser()) {
            $form = $this
                ->createForm(CommentType::class, $comment, [
                    'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
                ])
                ->createView();
        }

        return $this->render('Comment/list_and_form.html.twig', [
            'nb_comments' => $commentRepository->findNBCommentaires($event),
            'comments' => $commentRepository->findAllByEvent($event, $page, self::COMMENTS_PER_PAGE),
            'event' => $event,
            'page' => $page,
            'offset' => self::COMMENTS_PER_PAGE,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}/{page}", name="app_comment_list", requirements={"id": "\d+", "page": "\d+"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function list(Event $event, CommentRepository $commentRepository, int $page = 1)
    {
        return $this->render('Comment/list.html.twig', [
            'nb_comments' => $commentRepository->findNBCommentaires($event),
            'comments' => $commentRepository->findAllByEvent($event, $page, self::COMMENTS_PER_PAGE),
            'event' => $event,
            'page' => $page,
            'offset' => self::COMMENTS_PER_PAGE,
        ]);
    }

    /**
     * @Route("/{id}/nouveau", name="app_comment_new", requirements={"id": "\d+"})
     * @IsGranted("ROLE_USER")
     */
    public function newComment(Request $request, Event $event, CommentRepository $commentRepository)
    {
        $user = $this->getUser();
        $comment = new Comment();
        $comment->setUser($user);
        $comment->setEvent($event);

        $form = $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
        ]);

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
                    'nb_comments' => $commentRepository->findNBCommentaires($event),
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
}
