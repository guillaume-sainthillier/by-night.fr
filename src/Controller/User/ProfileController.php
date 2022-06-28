<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\UserEvent;
use App\Form\Type\ChangePasswordFormType;
use App\Form\Type\ProfileFormType;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/profile')]
class ProfileController extends AbstractController
{
    #[Route(path: '/delete', name: 'app_user_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, EventRepository $eventRepository, CommentRepository $commentRepository): Response
    {
        $form = $this->createDeleteForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getEntityManager();

            $deleteEvents = $form->get('delete_events')->getData();

            $user = $this->getAppUser();
            $events = $eventRepository->findBy([
                'user' => $user,
            ]);

            foreach ($events as $event) {
                if (!$deleteEvents) {
                    $event->setUser(null);
                } else {
                    $em->remove($event);
                }
            }

            $userEvents = $user->getUserEvents();
            foreach ($userEvents as $userEvent) {
                /** @var UserEvent $userEvent */
                $event = $userEvent->getEvent();
                if ($userEvent->getParticipe()) {
                    $event->setParticipations($event->getParticipations() - 1);
                } else {
                    $event->setInterets($event->getInterets() - 1);
                }
                $em->remove($userEvent);
            }

            $comments = $commentRepository->findAllByUser($user);
            foreach ($comments as $comment) {
                $em->remove($comment);
            }
            $em->flush();

            // TODO: Optimize flush & check constraints
            $em->remove($user);
            $em->flush();

            $this->addFlash('info', 'Votre compte a bien été supprimé. A bientôt sur By Night !');

            return $this->redirectToRoute('app_index');
        }
        $errors = $form->getErrors(true);
        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('app_user_edit');
    }

    #[Route(path: '/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getAppUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getEntityManager();
            $em->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour');
        }
        $formChangePassword = $this->createForm(ChangePasswordFormType::class, $user);
        $formChangePassword->handleRequest($request);
        if ($formChangePassword->isSubmitted() && $formChangePassword->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $formChangePassword->get('plainPassword')->getData()
                )
            );
            $em = $this->getEntityManager();
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour');
        }
        $formDelete = $this->createDeleteForm();

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'formChangePassword' => $formChangePassword->createView(),
            'formDelete' => $formDelete->createView(),
        ]);
    }

    private function createDeleteForm(): FormInterface
    {
        return $this
            ->createFormBuilder()
            ->add('delete_events', CheckboxType::class, [
                'required' => false,
            ])
            ->getForm();
    }
}
