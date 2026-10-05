<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * The page a member logs in from, which the login leads back to: a "log in to…" link names it as "?_target_path=",
 * kept in the session entry the firewall itself writes when a visitor reaches a page that needs a login. The form login
 * (UserFormAuthenticator), the sign-up (which logs in through it) and the social login (LoginSocialController) all read
 * that entry, whichever the member picks. A path of this site only: never another host.
 *
 * The path may end with a fragment, which the page reads once the member is back: "#participer" records the "J'y vais"
 * they clicked before logging in.
 */
final class LoginTargetPath
{
    use TargetPathTrait;

    private const string FIREWALL = 'main';

    public function saveFromQuery(Request $request): void
    {
        $path = $request->query->getString('_target_path');
        if (self::isLocalPath($path) && $request->hasSession()) {
            $this->saveTargetPath($request->getSession(), self::FIREWALL, $path);
        }
    }

    /**
     * The page to lead back to, forgotten at once: the next login goes to the default page again.
     */
    public function pull(SessionInterface $session): ?string
    {
        $path = $this->getTargetPath($session, self::FIREWALL);
        $this->removeTargetPath($session, self::FIREWALL);

        return $path;
    }

    private static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && !preg_match('/[\x00-\x1F\x7F]/', $path);
    }
}
