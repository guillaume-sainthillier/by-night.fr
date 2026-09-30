<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Form\Type\ChangePasswordFormType;
use App\Form\Type\DeleteAccountFormType;
use App\Form\Type\ProfileFormType;
use App\Manager\UserRemover;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Security\EmailVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route(path: '/profile')]
final class ProfileController extends AbstractController
{
    #[Route(path: '/delete', name: 'app_user_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, UserRemover $userRemover, TokenStorageInterface $tokenStorage): Response
    {
        $form = $this->createForm(DeleteAccountFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $userRemover->remove($this->getAppUser(), (bool) $form->get('delete_events')->getData());

            $this->addFlash('info', "Votre compte a bien été supprimé. À bientôt sur By Night\u{a0}!");

            $tokenStorage->setToken(null);

            return $this->redirectToRoute('app_index');
        }

        // The messages only: a FormError holds its form, which the session cannot serialize
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_user_edit', ['_fragment' => 'delete']);
    }

    #[Route(path: '/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserPasswordHasherInterface $passwordHasher, EventRepository $eventRepository, CommentRepository $commentRepository): Response
    {
        $user = $this->getAppUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getEntityManager();
            $em->flush();

            $this->addFlash('success', 'Votre profil a bien été mis à jour.');
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

            $this->addFlash('success', 'Votre mot de passe a bien été mis à jour.');
        }

        $formDelete = $this->createForm(DeleteAccountFormType::class);

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'formChangePassword' => $formChangePassword,
            'formDelete' => $formDelete,
            // The tab of the submitted form, so that its errors or its success message show
            'activeTab' => $formChangePassword->isSubmitted() ? 'password' : 'profile',
            'profileCompletion' => $user->getProfileCompletion(),
            // What the deletion erases, shown on its tab
            'favoritesCount' => $eventRepository->getUserFavoriteEventsCount($user),
            'commentsCount' => $commentRepository->count(['user' => $user]),
            'eventsCount' => $eventRepository->count(['user' => $user]),
        ]);
    }

    #[Route(path: '/mail-de-verification', name: 'app_send_verification_email', methods: ['POST'])]
    public function verifyUserEmail(EmailVerifier $emailVerifier): Response
    {
        $emailVerifier->sendEmailConfirmation($this->getAppUser());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
