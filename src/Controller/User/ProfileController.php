<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\User;

use App\Entity\Calendrier;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use FOS\UserBundle\Controller\ProfileController as BaseController;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Form\Factory\FactoryInterface;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProfileController extends BaseController
{
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    private FactoryInterface $profileFormFactory;

    /** @var UserManagerInterface */
    private $userManager;

    private FactoryInterface $changePasswordFormFactory;

    public function __construct(EventDispatcherInterface $eventDispatcher, FactoryInterface $profileFormFactory, UserManagerInterface $userManager, FactoryInterface $changePasswordFormFactory, EventRepository $eventRepository, CommentRepository $commentRepository)
    {
        parent::__construct($eventDispatcher, $profileFormFactory, $userManager);

        $this->eventDispatcher = $eventDispatcher;
        $this->profileFormFactory = $profileFormFactory;
        $this->userManager = $userManager;
        $this->changePasswordFormFactory = $changePasswordFormFactory;
    }

    /**
     * @Route("/show", name="fos_user_profile_show")
     */
    public function show()
    {
        return $this->redirectToRoute('fos_user_profile_edit');
    }

    /**
     * @Route("/delete", name="app_user_delete")
     *
     * @return RedirectResponse
     */
    public function delete(Request $request, UserManagerInterface $userManager, EventRepository $eventRepository, CommentRepository $commentRepository)
    {
        $user = $this->getUser();
        if (!\is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        $form = $this->createDeleteForm();
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $deleteEvents = $form->get('delete_events')->getData();
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

            $calendriers = $user->getCalendriers();
            foreach ($calendriers as $calendrier) {
                /** @var Calendrier $calendrier */
                $event = $calendrier->getEvent();
                if ($calendrier->getParticipe()) {
                    $event->setParticipations($event->getParticipations() - 1);
                } else {
                    $event->setInterets($event->getInterets() - 1);
                }
                $em->remove($calendrier);
            }

            $comments = $commentRepository->findAllByUser($user);
            foreach ($comments as $comment) {
                $em->remove($comment);
            }
            $em->flush();

            $userManager->deleteUser($user);

            $this->addFlash('info', 'Votre compte a bien été supprimé. A bientôt sur By Night !');

            return $this->redirectToRoute('app_main_index');
        }
        $errors = $form->getErrors(true);
        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('fos_user_profile_edit');
    }

    private function createDeleteForm()
    {
        return $this->createFormBuilder()
            ->add('delete_events', CheckboxType::class, [
                'required' => false,
            ])
            ->getForm();
    }

    /**
     * @Route("/edit", name="fos_user_profile_edit")
     *
     * @return RedirectResponse|Response|null
     */
    public function edit(Request $request)
    {
        $user = $this->getUser();
        if (!\is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        $event = new GetResponseUserEvent($user, $request);
        $this->eventDispatcher->dispatch($event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $this->profileFormFactory->createForm();
        $form->setData($user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = new FormEvent($form, $request);
            $this->eventDispatcher->dispatch($event);

            $this->userManager->updateUser($user);

            if (null === $response = $event->getResponse()) {
                $url = $this->generateUrl('fos_user_profile_edit');
                $response = new RedirectResponse($url);
            }

            $this->eventDispatcher->dispatch(new FilterUserResponseEvent($user, $request, $response));

            return $response;
        }

        $formChangePassword = $this->changePasswordFormFactory->createForm();
        $formChangePassword->setData($user);
        $formDelete = $this->createDeleteForm();

        return $this->render('@FOSUser/Profile/edit.html.twig', [
            'form' => $form->createView(),
            'formChangePassword' => $formChangePassword->createView(),
            'formDelete' => $formDelete->createView(),
        ]);
    }
}
