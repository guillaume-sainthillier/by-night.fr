<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Utils\Monitor;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class ConsoleSubscriber implements EventSubscriberInterface
{
    /** @var array<Stopwatch|null> */
    private array $stopwatches = [];

    public function __construct(
        #[Autowire(env: 'APP_MONITOR')]
        private readonly int $monitor,
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        Monitor::$output = $event->getOutput();
        Monitor::enableMonitoring((bool) $this->monitor);
        $this->stopwatches[$event->getCommand()->getName()] = Monitor::start($event->getCommand()->getName());
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        Monitor::stop($event->getCommand()->getName(), $this->stopwatches[$event->getCommand()->getName()]);
        Monitor::displayStats();
    }
}
