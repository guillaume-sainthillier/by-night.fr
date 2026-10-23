<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\User;
use App\Manager\Security\TwoFactorRecoveryManager;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * A user who lost their second factor asks, from the 2FA form, for a link e-mailed to them that turns it off
 * (App\Manager\Security\TwoFactorRecoveryManager)
 */
final class TwoFactorRecoveryController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'two_factor_recovery';

    #[Route(path: '/2fa/recuperation', name: 'app_two_factor_recovery_request', methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID, tokenKey: '_token')]
    public function request(
        Request $request,
        Security $security,
        TwoFactorRecoveryManager $recoveryManager,
        RateLimiterFactoryInterface $twoFactorRecoveryUserLimiter,
        RateLimiterFactoryInterface $twoFactorRecoveryIpLimiter,
        LoggerInterface $logger,
    ): RedirectResponse {
        $token = $security->getToken();
        $user = $token?->getUser();
        if (!$token instanceof TwoFactorTokenInterface || !$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $isUserAccepted = $twoFactorRecoveryUserLimiter->create((string) $user->getId())->consume()->isAccepted();
        $isIpAccepted = $twoFactorRecoveryIpLimiter->create((string) $request->getClientIp())->consume()->isAccepted();
        if ($isUserAccepted && $isIpAccepted) {
            $recoveryManager->sendRecoveryLink($user);
        } else {
            $logger->warning('2FA recovery request throttled.', ['user_id' => $user->getId(), 'ip' => $request->getClientIp()]);
        }

        // The same message either way: the throttling is not told
        $this->addFlash('info', 'Si votre compte peut être récupéré, un lien vient de vous être envoyé par e-mail. Il est valable une heure.');

        return $this->redirectToRoute('2fa_login');
    }

    #[Route(path: '/connexion/recuperation/{id<%patterns.id%>}', name: 'app_two_factor_recovery', methods: ['GET', 'POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID, tokenKey: '_token', methods: ['POST'])]
    public function recover(
        Request $request,
        Security $security,
        UserRepository $userRepository,
        TwoFactorRecoveryManager $recoveryManager,
        int $id,
    ): Response {
        $user = $userRepository->find($id);
        $isValid = $user instanceof User && $recoveryManager->isLinkValid($request, $user);

        // A GET only shows a confirmation: mail scanners that prefetch links must not burn them. The confirmation
        // posts back to the same signed URL, checked again.
        if (!$isValid || !$request->isMethod(Request::METHOD_POST)) {
            $response = $this->render('security/two-factor-recovery.html.twig', [
                'isValid' => $isValid,
            ], new Response(status: $request->isMethod(Request::METHOD_POST) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
            // The signed URL must not leak to the pages linked from here
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        }

        $recoveryManager->reset($user);
        $this->getEntityManager()->flush();

        if (null !== $security->getToken()) {
            $security->logout(false);
        }

        $this->addFlash('success', 'La double authentification de votre compte est désactivée : connectez-vous avec votre mot de passe.');

        return $this->redirectToRoute('app_login');
    }
}
