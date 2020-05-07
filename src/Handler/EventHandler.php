<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Entity\Event;
use App\Entity\Place;
use App\Exception\UnsupportedFileException;
use App\File\DeletableFile;
use App\Utils\Cleaner;
use App\Utils\Comparator;
use App\Utils\Merger;
use App\Utils\Monitor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use function GuzzleHttp\Psr7\copy_to_string;
use Psr\Log\LoggerInterface;

class EventHandler
{
    private Cleaner $cleaner;

    private Comparator $comparator;

    private Merger $merger;

    private LoggerInterface $logger;

    private string $tempPath;

    public function __construct(Cleaner $cleaner, Comparator $comparator, Merger $merger, LoggerInterface $logger, string $tempPath)
    {
        $this->cleaner = $cleaner;
        $this->comparator = $comparator;
        $this->merger = $merger;
        $this->logger = $logger;
        $this->tempPath = $tempPath;
    }

    public function cleanEvent(Event $event)
    {
        $this->cleaner->cleanEvent($event);
        if (null !== $event->getPlace()) {
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
        } catch (RequestException $e) {
            if ($e->getResponse() && 403 === $e->getResponse()->getStatusCode()) {
                return;
            }

            $this->logger->error($e->getMessage(), ['event' => [
                'id' => $event->getId(),
                'url' => $event->getUrl(),
                'exception' => $e,
            ]]);
        } catch (UnsupportedFileException $e) {
            $this->logger->error($e->getMessage(), ['event' => [
                'id' => $event->getId(),
                'url' => $event->getUrl(),
                'exception' => $e,
            ]]);
        }
    }

    /**
     * @param $content
     * @param $contentType
     *
     * @throws UnsupportedFileException
     */
    private function uploadFile(Event $event, $content, $contentType)
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
                throw new UnsupportedFileException(sprintf('Unable to find extension for mime type %s', $contentType));
        }

        $filename = ($event->getId() ?: uniqid()) . '.' . $ext;
        $tempPath = $this->tempPath . \DIRECTORY_SEPARATOR . $filename;
        $octets = \file_put_contents($tempPath, $content);

        if ($octets > 0) {
            $file = new DeletableFile($tempPath, $filename, $contentType, null, true);
            $event->setImageSystemFile($file);
        } else {
            unlink($tempPath);
            $event->setImageSystemFile(null);
        }
    }

    /**
     * @return Event
     */
    public function handle(array $persistedEvents, array $persistedPlaces, Event $event)
    {
        $place = Monitor::bench('Handle Place', fn () => $this->handlePlace($persistedPlaces, $event->getPlace()));
        $event->setPlace($place);

        return Monitor::bench('Handle Event', fn () => $this->handleEvent($persistedEvents, $event));
    }

    public function handlePlace(array $persistedPlaces, Place $notPersistedPlace)
    {
        $bestPlace = Monitor::bench('getBestPlace', fn () => $this->comparator->getBestPlace($persistedPlaces, $notPersistedPlace));

        //On fusionne la place existant avec celle découverte (même si NULL)
        return Monitor::bench('mergePlace', fn () => $this->merger->mergePlace($bestPlace, $notPersistedPlace));
    }

    public function handleEvent(array $persistedEvents, Event $notPersistedEvent)
    {
        $bestEvent = \count($persistedEvents) > 0 ? current($persistedEvents) : null;

        //On fusionne l'event existant avec celui découvert (même si NULL)
        return Monitor::bench('mergeEvent', fn () => $this->merger->mergeEvent($bestEvent, $notPersistedEvent));
    }
}
