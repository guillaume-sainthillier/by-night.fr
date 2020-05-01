<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Utils\Monitor;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ConsoleListener implements EventSubscriberInterface
{
    private int $monitor;

    /** @var Stopwatch[] */
    private array $stopwatches = [];

    public function __construct(int $monitor)
    {
        $this->monitor = $monitor;
    }

    public static function getSubscribedEvents()
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

    public function onConsoleTerminate(ConsoleTerminateEvent $event)
    {
        Monitor::stop($event->getCommand()->getName(), $this->stopwatches[$event->getCommand()->getName()]);
        Monitor::displayStats();
    }
}
