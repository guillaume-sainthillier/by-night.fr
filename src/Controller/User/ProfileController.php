<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 31/05/2016
 * Time: 19:26.
 */

namespace App\Controller\User;

use App\Entity\Agenda;
use App\Entity\Calendrier;
use App\Entity\Comment;
use FOS\UserBundle\Controller\ProfileController as BaseController;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Form\Factory\FactoryInterface;
use FOS\UserBundle\FOSUserEvents;
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

    /** @var FactoryInterface */
    private $formFactory;

    /** @var UserManagerInterface */
    private $userManager;

    public function __construct(EventDispatcherInterface $eventDispatcher, FactoryInterface $formFactory, UserManagerInterface $userManager)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->formFactory     = $formFactory;
        $this->userManager     = $userManager;

        parent::__construct($eventDispatcher, $formFactory, $userManager);
    }

    /**
     * @Route("/show", name="fos_user_profile_show")
     */
    public function showAction()
    {
        return $this->redirectToRoute('fos_user_profile_edit');
    }

    /**
     * @Route("/delete", name="tbn_user_delete")
     *
     * @param Request              $request
     * @param UserManagerInterface $userManager
     *
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, UserManagerInterface $userManager)
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
            $events       = $this->getDoctrine()->getRepository(Agenda::class)->findBy([
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
                $agenda = $calendrier->getAgenda();
                if ($calendrier->getParticipe()) {
                    $agenda->setParticipations($agenda->getParticipations() - 1);
                } else {
                    $agenda->setInterets($agenda->getInterets() - 1);
                }
                $em->remove($calendrier);
            }

            $comments = $this->getDoctrine()->getRepository(Comment::class)->findAllByUser($user);
            foreach ($comments as $comment) {
                $em->remove($comment);
            }
            $em->flush();

            $userManager->deleteUser($user);

            $this->addFlash('info', 'Votre compte a bien été supprimé. A bientôt sur By Night !');

            return $this->redirectToRoute('tbn_main_index');
        } else {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->redirectToRoute('fos_user_profile_edit');
    }

    /**
     * @Route("/edit", name="fos_user_profile_edit")
     *
     * @param Request $request
     *
     * @return null|RedirectResponse|Response
     */
    public function editAction(Request $request)
    {
        $user = $this->getUser();
        if (!\is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        $event = new GetResponseUserEvent($user, $request);
        $this->eventDispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $this->formFactory->createForm();
        $form->setData($user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = new FormEvent($form, $request);
            $this->eventDispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_SUCCESS, $event);

            $this->userManager->updateUser($user);

            if (null === $response = $event->getResponse()) {
                $url      = $this->generateUrl('fos_user_profile_show');
                $response = new RedirectResponse($url);
            }

            $this->eventDispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

            return $response;
        }

        /** @var $formFactory FactoryInterface */
        $formChangePasswordFactory = $this->get('fos_user.change_password.form.factory');
        $formChangePassword        = $formChangePasswordFactory->createForm();
        $formChangePassword->setData($user);
        $formDelete = $this->createDeleteForm();

        return $this->render('@FOSUser/Profile/edit.html.twig', array(
            'form'               => $form->createView(),
            'formChangePassword' => $formChangePassword->createView(),
            'formDelete'         => $formDelete->createView(),
        ));
    }

    private function createDeleteForm()
    {
        return $this->createFormBuilder()
            ->add('delete_events', CheckboxType::class, [
                'required' => false,
            ])
            ->getForm();
    }
}
