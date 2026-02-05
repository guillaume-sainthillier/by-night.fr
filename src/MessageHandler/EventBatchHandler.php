<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Dto\EventDto;
use App\Handler\DoctrineEventHandler;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Throwable;

#[AsMessageHandler]
final class EventBatchHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private readonly DoctrineEventHandler $doctrineEventHandler,
        private readonly LoggerInterface $logger,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(EventDto $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function process(array $jobs): void
    {
        $dtos = array_map(static fn (array $job): EventDto => $job[0], $jobs);

        try {
            Monitor::bench('ADD EVENT BATCH', function () use ($dtos): void {
                $this->doctrineEventHandler->handleManyCLI($dtos);
            });

            foreach ($jobs as [$dto, $ack]) {
                $ack->ack();
            }
        } catch (Throwable $e) {
            $this->logger->error('Batch processing failed, retrying one-by-one: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            /** @var EntityManagerInterface $em */
            $em = $this->managerRegistry->getManager();
            if (!$em->isOpen()) {
                $this->managerRegistry->resetManager();
            }

            foreach ($jobs as [$dto, $ack]) {
                try {
                    $this->doctrineEventHandler->handleOne($dto);
                    $ack->ack();
                } catch (Throwable $e) {
                    $this->logger->error('Individual event processing failed: {message}', [
                        'message' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                    $ack->nack($e);
                }
            }
        }
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function getBatchSize(): int
    {
        return 50;
    }
}
