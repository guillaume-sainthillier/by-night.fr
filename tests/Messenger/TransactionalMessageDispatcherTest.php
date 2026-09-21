<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Messenger;

use App\Messenger\TransactionalMessageDispatcher;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TransactionalMessageDispatcherTest extends TestCase
{
    /** @var object[] */
    private array $sent = [];

    private TransactionalMessageDispatcher $dispatcher;

    protected function setUp(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus
            ->method('dispatch')
            ->willReturnCallback(function (object $message, array $stamps = []): Envelope {
                $this->sent[] = $message;

                return new Envelope($message, $stamps);
            });

        $this->dispatcher = new TransactionalMessageDispatcher($bus);
    }

    public function testDispatchesImmediatelyOutsideATransaction(): void
    {
        $message = new stdClass();

        $this->dispatcher->dispatch($message);

        $this->assertSame([$message], $this->sent);
    }

    public function testHoldsMessagesUntilCommit(): void
    {
        $first = new stdClass();
        $second = new stdClass();

        $this->dispatcher->begin();
        $this->dispatcher->dispatch($first);
        $this->dispatcher->dispatch($second);

        $this->assertSame([], $this->sent, 'Nothing leaves while the transaction is open');

        $this->dispatcher->commit();

        $this->assertSame([$first, $second], $this->sent, 'Released in order once committed');

        // Back to pass-through
        $third = new stdClass();
        $this->dispatcher->dispatch($third);

        $this->assertSame([$first, $second, $third], $this->sent);
    }

    public function testRollBackDropsHeldMessages(): void
    {
        $this->dispatcher->begin();
        $this->dispatcher->dispatch(new stdClass());

        $this->dispatcher->rollBack();
        $this->dispatcher->commit();

        $this->assertSame([], $this->sent, 'A rolled back batch must not emit anything, even on a later commit');
    }
}
