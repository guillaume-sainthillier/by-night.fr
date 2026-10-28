<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The access listener of scheb/2fa reads the token on every request of the firewall, to deny the pages to a member
 * between their password and their second factor. Through security.token_storage, that read counts as a use of the
 * session, even for a visitor without one: every page turned private, and a stateless route (the outings calendar)
 * answered a 500. The untracked storage holds the same token, still loaded by the lazy firewall, and is the one
 * Symfony's own ContextListener uses: only what a page reads of the member (getUser(), is_granted()) uses the
 * session. The listener changes a response only to deny it, and its redirect to /2fa keeps the target path in the
 * session, private. Proposed upstream: https://github.com/scheb/2fa/pull/327.
 */
final class TwoFactorAccessListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // The listener of each firewall is a child of this definition, resolved after this pass
        $container
            ->getDefinition('scheb_two_factor.security.access_listener')
            ->replaceArgument(1, new Reference('security.untracked_token_storage'))
        ;
    }
}
