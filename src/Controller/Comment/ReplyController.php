<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Comment;

use App\Controller\AbstractController as BaseController;
use App\Entity\Comment;
use App\Form\Type\CommentType;
use App\Repository\CommentRepository;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReplyController extends BaseController
{
    /**
     * @var int
     */
    public const REPLIES_PER_PAGE = 5;

    #[Route(path: '/{id<%patterns.id%>}/reponses/{page<%patterns.page%>}', name: 'app_comment_reponse_list', methods: ['GET'])]
    public function list(Comment $comment, CommentRepository $commentRepository, PaginatorInterface $paginator, int $page = 1): Response
    {
        $comments = $paginator->paginate(
            $commentRepository->findAllAnswersQuery($comment),
            $page,
            self::REPLIES_PER_PAGE
        );

        return $this->render('comment/reply/list.html.twig', [
            'comments' => $comments,
            'mainComment' => $comment,
            'page' => $page,
            'offset' => self::REPLIES_PER_PAGE,
        ]);
    }

    #[Route(path: '/{id<%patterns.id%>}/repondre', name: 'app_comment_reponse_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, Comment $comment): Response
    {
        $user = $this->getAppUser();
        $reponse = new Comment();
        $reponse->setUser($user);
        $reponse->setEvent($comment->getEvent());

        $form = $this->createForm(CommentType::class, $reponse, [
            'action' => $this->generateUrl('app_comment_reponse_new', ['id' => $comment->getId()]),
        ]);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $reponse->setParent($comment);
            $comment->addReponse($reponse);
            $em = $this->getEntityManager();
            $em->persist($comment);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'comment' => $this->renderView('comment/reply/details.html.twig', [
                    'comment' => $reponse,
                    'success' => true,
                ]),
            ]);
        } elseif ($form->isSubmitted()) {
            return new JsonResponse([
                'success' => false,
                'post' => $this->renderView('comment/reply/form.html.twig', [
                    'comment' => $comment,
                    'form' => $form->createView(),
                ]),
            ]);
        }

        return $this->render('comment/reply/form.html.twig', [
            'comment' => $comment,
            'form' => $form->createView(),
        ]);
    }
}
