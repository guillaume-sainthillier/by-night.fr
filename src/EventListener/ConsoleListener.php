<?php

namespace App\EventListener;

use App\Utils\Monitor;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsoleListener implements EventSubscriberInterface
{
    private $stopwatches = [];

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
        Monitor::enableMonitoring($event->getOutput()->isVerbose());
        $this->stopwatches[$event->getCommand()->getName()] = Monitor::start($event->getCommand()->getName());
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event)
    {
        Monitor::stop($event->getCommand()->getName(), $this->stopwatches[$event->getCommand()->getName()]);
        Monitor::displayStats();
    }
}
