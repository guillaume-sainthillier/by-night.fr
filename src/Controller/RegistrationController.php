<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Security\UserFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager, private EmailVerifier $emailVerifier)
    {
        parent::__construct($entityManager);
    }

    #[Route(path: '/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function index(Request $request, UserPasswordHasherInterface $passwordHasher, GuardAuthenticatorHandler $guardHandler, UserFormAuthenticator $authenticator): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user
                ->setFromLogin(true)
                ->setPassword(
                    $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager = $this->getEntityManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation($user);
            // do anything else you need here, like send an email

            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/verifier-email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verifyUserEmail(Request $request): Response
    {
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $this->getAppUser());
        } catch (VerifyEmailExceptionInterface $verifyEmailException) {
            $this->addFlash('error', $verifyEmailException->getReason());

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Your email a bien été vérifié.');

        return $this->redirectToRoute('app_event_list');
    }
}
