<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CommentController extends BaseController
{
    public const int COMMENTS_PER_PAGE = 10;

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

        $comments = $this->createQueryBuilderPaginator(
            $commentRepository->findAllByEventQueryBuilder($event),
            $page,
            self::COMMENTS_PER_PAGE
        );

        return $this->render('comment/list-and-form.html.twig', [
            'comments' => $comments,
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id<%patterns.id%>}/{page<%patterns.page%>}', name: 'app_comment_list', methods: ['GET'])]
    #[ReverseProxy(expires: 'tomorrow')]
    public function list(Event $event, CommentRepository $commentRepository, int $page = 1): Response
    {
        $comments = $this->createQueryBuilderPaginator(
            $commentRepository->findAllByEventQueryBuilder($event),
            $page,
            self::COMMENTS_PER_PAGE
        );

        return $this->render('comment/list.html.twig', [
            'comments' => $comments,
            'event' => $event,
            'page' => $page,
            'offset' => self::COMMENTS_PER_PAGE,
        ]);
    }

    #[Route(path: '/{id<%patterns.id%>}/nouveau', name: 'app_comment_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, Event $event, CommentRepository $commentRepository): Response
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
            $em = $this->getEntityManager();
            $em->persist($comment);
            $em->flush();

            $comments = $this->createQueryBuilderPaginator(
                $commentRepository->findAllByEventQueryBuilder($event),
                1,
                self::COMMENTS_PER_PAGE
            );

            return new JsonResponse([
                'success' => true,
                'comment' => $this->renderView('comment/details.html.twig', [
                    'comment' => $comment,
                    'success' => true,
                ]),
                'header' => $this->renderView('comment/header.html.twig', [
                    'comments' => $comments,
                ]),
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'post' => $this->renderView('comment/form.html.twig', [
                'form' => $form->createView(),
            ]),
        ]);
    }
}
