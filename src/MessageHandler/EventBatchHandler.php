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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    /**
     * @param array<array{EventDto, Acknowledger}> $jobs
     *
     * @phpstan-ignore method.unused (called by BatchHandlerTrait)
     */
    private function process(array $jobs): void
    {
        $dtos = array_map(static fn (array $job): EventDto => $job[0], $jobs);

        try {
            $this->handleBatch($dtos);
        } catch (UniqueConstraintViolationException $e) {
            // Another worker committed the same place, tag or exploration while this
            // batch was running. The whole batch was rolled back, and on a second run
            // the lookups find those rows instead of inserting them, so one re-run is
            // normally all it takes. With concurrent workers this is expected, not an error.
            $this->logger->warning('Import batch hit a unique key written by a concurrent worker, re-running it once: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            try {
                $this->handleBatch($dtos);
            } catch (Throwable $e) {
                $this->handleOneByOne($jobs, $e);

                return;
            }
        } catch (Throwable $e) {
            $this->handleOneByOne($jobs, $e);

            return;
        }

        foreach ($jobs as [, $ack]) {
            $ack->ack();
        }
    }

    /**
     * @param EventDto[] $dtos
     */
    private function handleBatch(array $dtos): void
    {
        $this->resetClosedManager();

        Monitor::bench('ADD EVENT BATCH', function () use ($dtos): void {
            $this->doctrineEventHandler->handleManyCLI($dtos);
        });
    }

    /**
     * Last resort once the batch failed as a whole: messages are processed and
     * acknowledged one at a time so a single bad event cannot block the others.
     *
     * @param array<array{EventDto, Acknowledger}> $jobs
     */
    private function handleOneByOne(array $jobs, Throwable $batchException): void
    {
        $this->logger->error('Batch processing failed, retrying one-by-one: {message}', [
            'message' => $batchException->getMessage(),
            'exception' => $batchException,
        ]);

        foreach ($jobs as [$dto, $ack]) {
            try {
                $this->resetClosedManager();
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

    /**
     * A failed flush closes the EntityManager: it has to be reset before any retry,
     * otherwise every following attempt fails with "EntityManager is closed".
     */
    private function resetClosedManager(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->managerRegistry->getManager();
        if (!$em->isOpen()) {
            $this->managerRegistry->resetManager();
        }
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function getBatchSize(): int
    {
        return 50;
    }
}
