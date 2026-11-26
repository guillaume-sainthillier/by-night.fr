<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventListener;

use App\App\AppContext;
use App\App\CountrySlugs;
use App\App\LazyLocationFactory;
use App\EventListener\AppContextListener;
use App\Tests\AppKernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The error page of a URL without location (the API's reads among them, stateless) never looks up the member: their
 * city would open their session, and a stateless request answers 500 instead of its error.
 */
final class AppContextListenerErrorTest extends AppKernelTestCase
{
    public function testAnErrorWithoutLocationLeavesTheMemberAlone(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');
        $listener = new AppContextListener(
            new AppContext($security),
            self::getContainer()->get(LazyLocationFactory::class),
            self::getContainer()->get(CountrySlugs::class),
        );

        $request = Request::create('/api/events?city=nulle-part');
        $request->attributes->set('_stateless', true);
        $listener->onKernelException(new ExceptionEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, new NotFoundHttpException()));
    }
}
