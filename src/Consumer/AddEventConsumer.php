<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use App\Handler\DoctrineEventHandler;
use App\Producer\EventInErrorProducer;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Exception\AckStopConsumerException;
use Psr\Log\LoggerInterface;

class AddEventConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    public function __construct(
        LoggerInterface $logger,
        private EventInErrorProducer $eventInErrorProducer,
        private DoctrineEventHandler $doctrineEventHandler,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-return 1|-1
     */
    public function batchExecute(array $messages): int
    {
        $dtos = [];
        foreach ($messages as $message) {
            $dtos[] = unserialize($message->getBody());
        }

        try {
            Monitor::bench('ADD EVENT BATCH', function () use ($dtos) {
                $this->doctrineEventHandler->handleManyCLI($dtos);
            });
            Monitor::displayStats();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);

            try {
                foreach ($dtos as $dto) {
                    $this->eventInErrorProducer->scheduleEvent($dto);
                }
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            if (!$this->entityManager->isOpen()) {
                throw new AckStopConsumerException('EM is closed');
            }
        }

        return ConsumerInterface::MSG_ACK;
    }
}
