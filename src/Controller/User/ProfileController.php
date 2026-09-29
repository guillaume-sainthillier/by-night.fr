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
use App\Entity\User;
use App\Entity\UserEvent;
use App\Form\Type\ChangePasswordFormType;
use App\Form\Type\ProfileFormType;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Security\EmailVerifier;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route(path: '/profile')]
final class ProfileController extends AbstractController
{
    /** The word the member types to confirm the deletion of their account */
    public const string DELETE_CONFIRMATION = 'SUPPRIMER';

    #[Route(path: '/delete', name: 'app_user_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, EventRepository $eventRepository, CommentRepository $commentRepository, TokenStorageInterface $tokenStorage): Response
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
                if ($userEvent->getGoing()) {
                    $event->setParticipations($event->getParticipations() - 1);
                } else {
                    $event->setInterests($event->getInterests() - 1);
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

        $formDelete = $this->createDeleteForm();

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'formChangePassword' => $formChangePassword,
            'formDelete' => $formDelete,
            // The tab of the submitted form, so that its errors or its success message show
            'activeTab' => $formChangePassword->isSubmitted() ? 'password' : 'profile',
            'profileCompletion' => $this->getProfileCompletion($user),
            // What the deletion erases, shown on its tab
            'favoritesCount' => $eventRepository->getUserFavoriteEventsCount($user),
            'commentsCount' => $commentRepository->count(['user' => $user]),
            'eventsCount' => $eventRepository->count(['user' => $user]),
        ]);
    }

    /**
     * How complete the profile is, in percent: the picture, the names, the presentation and a verified e-mail address.
     */
    private function getProfileCompletion(User $user): int
    {
        // Empty strings (older rows) count as not filled, like null
        $fields = [
            $user->hasImage(),
            $user->getFirstname(),
            $user->getLastname(),
            $user->getDescription(),
            $user->isVerified(),
        ];

        return (int) round(100 * \count(array_filter($fields)) / \count($fields));
    }

    #[Route(path: '/mail-de-verification', name: 'app_send_verification_email', methods: ['POST'])]
    public function verifyUserEmail(EmailVerifier $emailVerifier): Response
    {
        $emailVerifier->sendEmailConfirmation($this->getAppUser());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function createDeleteForm(): FormInterface
    {
        $confirmationMessage = \sprintf('Tapez %s pour confirmer la suppression de votre compte.', self::DELETE_CONFIRMATION);

        return $this
            ->createFormBuilder()
            ->add('delete_events', CheckboxType::class, [
                'required' => false,
            ])
            ->add('confirmation', TextType::class, [
                'constraints' => [
                    new NotBlank(message: $confirmationMessage),
                    new EqualTo(value: self::DELETE_CONFIRMATION, message: $confirmationMessage),
                ],
            ])
            ->getForm();
    }
}
