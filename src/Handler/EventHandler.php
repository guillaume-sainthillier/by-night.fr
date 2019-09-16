<?php

namespace App\Handler;

use App\Entity\Event;
use App\Entity\Place;
use App\Utils\Cleaner;
use App\Utils\Comparator;
use App\Utils\Merger;
use App\Utils\Monitor;
use GuzzleHttp\Client;
use function GuzzleHttp\Psr7\copy_to_string;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Description of EventHandler.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class EventHandler
{
    private $cleaner;

    private $comparator;

    private $merger;

    private $logger;

    private $tempPath;

    public function __construct(Cleaner $cleaner, Comparator $comparator, Merger $merger, LoggerInterface $logger, $tempPath)
    {
        $this->cleaner = $cleaner;
        $this->comparator = $comparator;
        $this->merger = $merger;
        $this->logger = $logger;
        $this->tempPath = $tempPath;
    }

    public function uploadFile(Event $event, $content, $contentType)
    {
        switch ($contentType) {
            case 'image/gif':
                $ext = 'gif';
                break;
            case 'image/png':
                $ext = 'png';
                break;
            case 'image/jpg':
            case 'image/jpeg':
                $ext = 'jpeg';
                break;
            default:
                throw new \RuntimeException(sprintf('Unable to find extension for mime type %s', $contentType));
        }

        $filename = ($event->getId() ?: uniqid()) . '.' . $ext;
        $tempPath = $this->tempPath . \DIRECTORY_SEPARATOR . $filename;
        $octets = \file_put_contents($tempPath, $content);

        if ($octets > 0) {
            $file = new UploadedFile($tempPath, $filename, $contentType, null, true);
            $event->setSystemPath($filename);
            $event->setSystemFile($file);
        } else {
            $event->setSystemFile(null)->setSystemPath(null);
        }
    }

    public function cleanEvent(Event $event)
    {
        $this->cleaner->cleanEvent($event);
        if ($event->getPlace()) {
            $this->cleaner->cleanPlace($event->getPlace());
        }
    }

    public function handleDownload(Event $event)
    {
        try {
            $client = new Client();
            $url = $event->getUrl();
            $response = $client->request('GET', $url);
            $contentType = current($response->getHeader('Content-Type'));
            $content = copy_to_string($response->getBody());
            $this->uploadFile($event, $content, $contentType);
        } catch (\Exception $e) {
            $this->logger->error($e, ['event' => [
                'id' => $event->getId(),
                'url' => $event->getUrl(),
            ]]);
        }
    }

    /**
     * @return Event
     */
    public function handle(array $persistedEvents, array $persistedPlaces, Event $event)
    {
        $place = Monitor::bench('Handle Place', function () use ($persistedPlaces, $event) {
            return $this->handlePlace($persistedPlaces, $event->getPlace());
        });
        $event->setPlace($place);

        $event = Monitor::bench('Handle Event', function () use ($persistedEvents, $event) {
            return $this->handleEvent($persistedEvents, $event);
        });

        return $event;
    }

    public function handleEvent(array $persistedEvents, Event $notPersistedEvent)
    {
        $bestEvent = \count($persistedEvents) > 0 ? current($persistedEvents) : null;

        //On fusionne l'event existant avec celui découvert (même si NULL)
        return Monitor::bench('mergeEvent', function () use ($bestEvent, $notPersistedEvent) {
            return $this->merger->mergeEvent($bestEvent, $notPersistedEvent);
        });
    }

    public function handlePlace(array $persistedPlaces, Place $notPersistedPlace)
    {
        $bestPlace = Monitor::bench('getBestPlace', function () use ($persistedPlaces, $notPersistedPlace) {
            return $this->comparator->getBestPlace($persistedPlaces, $notPersistedPlace);
        });

        //On fusionne la place existant avec celle découverte (même si NULL)
        return Monitor::bench('mergePlace', function () use ($bestPlace, $notPersistedPlace) {
            return $this->merger->mergePlace($bestPlace, $notPersistedPlace);
        });
    }

    /**
     * @return Comparator
     */
    public function getComparator()
    {
        return $this->comparator;
    }
}
