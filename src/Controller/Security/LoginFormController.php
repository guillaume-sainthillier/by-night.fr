<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Security;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LoginFormController extends AbstractController
{
    use TargetPathTrait;

    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // The page a "log in to…" link comes from ("J'y vais", comments): the login, by form or social network, leads
        // back to it. A path of this site only, never another host
        $targetPath = $request->query->getString('_target_path');
        if (self::isLocalPath($targetPath)) {
            $this->saveTargetPath($request->getSession(), 'main', $targetPath);
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    private static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && !preg_match('/[\x00-\x1F\x7F]/', $path);
    }
}
