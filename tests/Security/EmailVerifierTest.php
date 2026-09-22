<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Security;

use App\Factory\UserFactory;
use App\Security\EmailVerifier;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('mjml')]
final class EmailVerifierTest extends AppKernelTestCase
{
    public function testTheConfirmationEmailStatesHowLongTheLinkIsValid(): void
    {
        $user = UserFactory::createOne();

        self::getContainer()->get(EmailVerifier::class)->sendEmailConfirmation($user);

        self::assertEmailCount(1);
        // The e-mail used to print the hour of day the link expires ("12 heure(s)") instead of its lifetime.
        self::assertEmailHtmlBodyContains(self::getMailerMessage(), 'Ce lien est valable 1 heure.');
    }
}
