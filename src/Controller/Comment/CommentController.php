<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Comment;

use App\Annotation\ReverseProxy;
use App\Controller\AbstractController as BaseController;
use App\Entity\Comment;
use App\Entity\Event;
use App\Form\Type\CommentType;
use App\Repository\CommentRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends BaseController
{
    public const COMMENTS_PER_PAGE = 10;

    #[Route(path: '/form/{id<%patterns.id%>}', name: 'app_comment_form', methods: ['GET'])]
    public function form(Event $event, CommentRepository $commentRepository, int $page = 1): Response
    {
        $comment = new Comment();
        $form = null;
        if ($this->isGranted('ROLE_USER')) {
            $form = $this
                ->createForm(CommentType::class, $comment, [
                    'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
                ])
                ->createView();
        }

        return $this->render('comment/list-and-form.html.twig', [
            'nb_comments' => $commentRepository->getCommentsCount($event),
            'comments' => $commentRepository->findAllByEvent($event, $page, self::COMMENTS_PER_PAGE),
            'event' => $event,
            'page' => $page,
            'offset' => self::COMMENTS_PER_PAGE,
            'form' => $form,
        ]);
    }

    /**
     * @ReverseProxy(expires="tomorrow")
     */
    #[Route(path: '/{id<%patterns.id%>}/{page<%patterns.page%>}', name: 'app_comment_list', methods: ['GET'])]
    public function list(Event $event, CommentRepository $commentRepository, int $page = 1): Response
    {
        return $this->render('comment/list.html.twig', [
            'nb_comments' => $commentRepository->getCommentsCount($event),
            'comments' => $commentRepository->findAllByEvent($event, $page, self::COMMENTS_PER_PAGE),
            'event' => $event,
            'page' => $page,
            'offset' => self::COMMENTS_PER_PAGE,
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     */
    #[Route(path: '/{id<%patterns.id%>}/nouveau', name: 'app_comment_new', methods: ['GET', 'POST'])]
    public function newComment(Request $request, Event $event, CommentRepository $commentRepository): Response
    {
        $user = $this->getAppUser();
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
                    'nb_comments' => $commentRepository->getCommentsCount($event),
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
