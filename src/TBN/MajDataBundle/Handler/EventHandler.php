<?php

namespace TBN\MajDataBundle\Handler;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MajDataBundle\Utils\Cleaner;
use TBN\MajDataBundle\Utils\Comparator;
use TBN\MajDataBundle\Utils\Merger;
use TBN\MajDataBundle\Utils\Monitor;

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
    private $tempPath;

    public function __construct(Cleaner $cleaner, Comparator $comparator, Merger $merger, $tempPath)
    {
        $this->cleaner    = $cleaner;
        $this->comparator = $comparator;
        $this->merger     = $merger;
        $this->tempPath   = $tempPath;
    }

    public function hasToDownloadImage($newURL, Agenda $agenda)
    {
        return $newURL && (
            !$agenda->getSystemPath() ||
            $agenda->getUrl() != $newURL
        );
    }

    public function uploadFile(Agenda $agenda, $content)
    {
        if (!$content) {
            $agenda->setUrl(null);
        } else {
            //En cas d'url du type:  http://u.rl/image.png?params
            $ext = preg_replace("/(\?|_)(.*)$/", '', pathinfo($agenda->getUrl(), PATHINFO_EXTENSION));

            $filename = sha1(uniqid(mt_rand(), true)).'.'.$ext;

            $tempPath = $this->tempPath.'/'.$filename;
            $octets   = file_put_contents($tempPath, $content);

            if ($octets > 0) {
                $file = new UploadedFile($tempPath, $filename, null, null, false, true);
                $agenda->setSystemPath($filename);
                $agenda->setSystemFile($file);
            } else {
                $agenda->setSystemFile(null)->setSystemPath(null);
            }
        }
    }

    public function cleanPlace(Place $place)
    {
        $this->cleaner->cleanPlace($place);
    }

    public function cleanEvent(Agenda $event)
    {
        $this->cleaner->cleanEvent($event);
        if ($event->getPlace()) {
            $this->cleaner->cleanPlace($event->getPlace());
        }
    }

    /**
     * @param array  $persistedEvents
     * @param array  $persistedPlaces
     * @param Agenda $event
     *
     * @return mixed|null
     */
    public function handle(array $persistedEvents, array $persistedPlaces, Agenda $event)
    {
        $place = Monitor::bench('Handle Place', function () use ($persistedPlaces, $event) {
            return  $this->handlePlace($persistedPlaces, $event->getPlace());
        });
        $event->setPlace($place);

        $event = Monitor::bench('Handle Event', function () use ($persistedEvents, $event) {
            return $this->handleEvent($persistedEvents, $event);
        });

        return $event;
    }

    public function handleEvent(array $persistedEvents, Agenda $notPersistedEvent)
    {
        //Evenement persisté
        $bestEvent = Monitor::bench('getBestEvent', function () use ($persistedEvents, $notPersistedEvent) {
            return $this->comparator->getBestEvent($persistedEvents, $notPersistedEvent);
        });

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
}
