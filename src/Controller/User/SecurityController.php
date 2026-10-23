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
use App\Entity\WebauthnKey;
use App\Form\Type\PasskeyRegistrationType;
use App\Form\Type\PasskeyType;
use App\Form\Type\RecoveryCodesType;
use App\Form\Type\TotpActivationType;
use App\Manager\Security\QrCodeManager;
use App\Manager\Security\TwoFactorSetupManager;
use App\Security\TwoFactor\Webauthn\WebauthnRegistration;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Throwable;

/**
 * The second factors of the logged-in member, all opt-in: the recovery codes first, then an authenticator app (TOTP)
 * and/or passkeys. Once one of these is on, each login asks for it (config/packages/scheb_2fa.yaml). Their overview
 * is the "Sécurité" tab of the account (profile/_security.html.twig), where each page here leads back.
 */
#[Route(path: '/profile/securite')]
final class SecurityController extends AbstractController
{
    /**
     * The codes are generated once per setup and shown until confirmed: only the confirmation saves them, replacing
     * the previous ones
     */
    #[Route(path: '/codes-de-secours', name: 'app_user_security_recovery_codes', methods: ['GET', 'POST'])]
    public function recoveryCodes(Request $request, TwoFactorSetupManager $setupManager): Response
    {
        $user = $this->getAppUser();
        $codes = $setupManager->getOrGenerateRecoveryCodes();

        $form = $this->createForm(RecoveryCodesType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setBackupCodes($codes);
            $this->getEntityManager()->flush();
            $setupManager->clearRecoveryCodes();

            $this->addFlash('success', 'Vos codes de secours sont enregistrés.');

            return $this->redirectToSecurityTab();
        }

        return $this->render('profile/security/recovery-codes.html.twig', [
            'form' => $form,
            'codes' => $codes,
        ]);
    }

    #[Route(path: '/codes-de-secours/telecharger', name: 'app_user_security_recovery_codes_download', methods: ['GET'])]
    public function downloadRecoveryCodes(TwoFactorSetupManager $setupManager): Response
    {
        if (!$setupManager->hasPendingRecoveryCodes()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getAppUser();
        $body = implode("\n", [
            'Codes de secours By Night',
            $this->generateUrl('app_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'Compte : ' . $user->getEmail(),
            '',
            'Chaque code remplace une fois votre application d\'authentification ou votre clé d\'accès :',
            '',
            ...array_map(static fn (string $code): string => '  ' . $code, $setupManager->getOrGenerateRecoveryCodes()),
            '',
            'Gardez ce fichier en lieu sûr : il donne accès à votre compte avec votre mot de passe.',
            '',
        ]);

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'by-night-codes-de-secours.txt'),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route(path: '/codes-de-secours/supprimer', name: 'app_user_security_recovery_codes_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('two-factor-recovery-codes-delete', tokenKey: '_token')]
    public function deleteRecoveryCodes(): RedirectResponse
    {
        $this->getAppUser()->setBackupCodes(null);
        $this->getEntityManager()->flush();

        $this->addFlash('success', 'Vos codes de secours sont supprimés.');

        return $this->redirectToSecurityTab();
    }

    #[Route(path: '/application', name: 'app_user_security_totp', methods: ['GET', 'POST'])]
    public function totp(
        Request $request,
        TwoFactorSetupManager $setupManager,
        GoogleAuthenticatorInterface $googleAuthenticator,
        QrCodeManager $qrCodeManager,
        RateLimiterFactoryInterface $twoFactorTotpVerificationLimiter,
    ): Response {
        $user = $this->getAppUser();
        if (null !== ($redirect = $this->requireRecoveryCodes($user))) {
            return $redirect;
        }

        $form = $this->createForm(TotpActivationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            // Every attempt counts, before its validity (TotpCode) is read: a throttled one is refused whether or not
            // its code is right
            if (!$twoFactorTotpVerificationLimiter->create((string) $user->getId())->consume()->isAccepted()) {
                $this->addFlash('danger', 'Trop de codes essayés : réessayez dans quelques minutes.');

                return $this->redirectToRoute('app_user_security_totp');
            }

            if ($form->isValid()) {
                $user->setGoogleAuthenticatorSecret($setupManager->getOrGenerateSecret());
                $this->getEntityManager()->flush();
                $setupManager->clearSecret();
                $this->addFlash('success', 'L\'application d\'authentification est activée : elle vous sera demandée à chaque connexion.');

                return $this->redirectToSecurityTab();
            }
        }

        return $this->render('profile/security/totp.html.twig', [
            'form' => $form,
            'secret' => $setupManager->getOrGenerateSecret(),
            'qrCode' => $qrCodeManager->toSvgDataUri($googleAuthenticator->getQRContent($setupManager->getPendingTotp($user))),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(path: '/application/desactiver', name: 'app_user_security_totp_disable', methods: ['POST'])]
    #[IsCsrfTokenValid('two-factor-totp-disable', tokenKey: '_token')]
    public function disableTotp(): RedirectResponse
    {
        $this->getAppUser()->setGoogleAuthenticatorSecret(null);
        $this->getEntityManager()->flush();

        $this->addFlash('success', 'L\'application d\'authentification est désactivée.');

        return $this->redirectToSecurityTab();
    }

    #[Route(path: '/cles/nouvelle', name: 'app_user_security_passkey_new', methods: ['GET', 'POST'])]
    public function newPasskey(Request $request, WebauthnRegistration $registration, LoggerInterface $logger): Response
    {
        $user = $this->getAppUser();
        if (null !== ($redirect = $this->requireRecoveryCodes($user))) {
            return $redirect;
        }

        $form = $this->createForm(PasskeyRegistrationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $name = (string) $form->get('name')->getData();
            if (null !== $user->getWebauthnKeys()->findFirst(static fn (int $i, WebauthnKey $key): bool => $key->getName() === $name)) {
                $form->get('name')->addError(new FormError('Vous avez déjà une clé d\'accès de ce nom.'));
            } else {
                try {
                    $registration->register($user, (string) $form->get('credential')->getData(), $name);
                    $this->getEntityManager()->flush();

                    $this->addFlash('success', 'La clé d\'accès est enregistrée : elle vous sera proposée à chaque connexion.');

                    return $this->redirectToSecurityTab();
                } catch (Throwable $e) {
                    $logger->notice('Passkey registration refused.', ['exception' => $e, 'user_id' => $user->getId()]);
                    $form->addError(new FormError('Votre clé d\'accès n\'a pas pu être vérifiée : réessayez.'));
                }
            }
        }

        // A new challenge on each display: the previous one was used, or never answered
        return $this->render('profile/security/passkey-new.html.twig', [
            'form' => $form,
            'creationOptions' => $registration->createOptionsJson($user),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(path: '/cles/{uuid<' . Requirement::UUID . '>}', name: 'app_user_security_passkey_edit', methods: ['GET', 'POST'])]
    public function editPasskey(Request $request, string $uuid): Response
    {
        $key = $this->findPasskey($uuid);
        $form = $this->createForm(PasskeyType::class, $key);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getEntityManager()->flush();
            $this->addFlash('success', 'La clé d\'accès est renommée.');

            return $this->redirectToSecurityTab();
        }

        return $this->render('profile/security/passkey-edit.html.twig', [
            'form' => $form,
            'key' => $key,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(path: '/cles/{uuid<' . Requirement::UUID . '>}/supprimer', name: 'app_user_security_passkey_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('two-factor-passkey-delete', tokenKey: '_token')]
    public function deletePasskey(string $uuid): RedirectResponse
    {
        $this->getAppUser()->removeWebauthnKey($this->findPasskey($uuid));
        $this->getEntityManager()->flush();

        $this->addFlash('success', 'La clé d\'accès est supprimée.');

        return $this->redirectToSecurityTab();
    }

    /**
     * The recovery codes come first: they are the way back in when the phone or the passkey is lost
     */
    private function requireRecoveryCodes(User $user): ?RedirectResponse
    {
        if ($user->isBackupCodeEnabled()) {
            return null;
        }

        $this->addFlash('info', "Enregistrez d'abord vos codes de secours : ils vous permettront de vous connecter si vous perdez votre second facteur.");

        return $this->redirectToRoute('app_user_security_recovery_codes');
    }

    /**
     * Among the user's own passkeys only: the UUID of someone else's is a 404
     */
    private function findPasskey(string $uuid): WebauthnKey
    {
        return $this->getAppUser()->getWebauthnKeys()->findFirst(
            static fn (int $i, WebauthnKey $key): bool => $key->getUuid()->toString() === $uuid,
        ) ?? throw $this->createNotFoundException();
    }

    private function redirectToSecurityTab(): RedirectResponse
    {
        return $this->redirectToRoute('app_user_edit', ['_fragment' => 'security']);
    }
}
