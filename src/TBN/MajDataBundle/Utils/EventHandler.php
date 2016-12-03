<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * Description of EventHandler
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class EventHandler
{
    private $firewall;
    private $cleaner;
    private $comparator;
    private $merger;
    private $tempPath;

    public function __construct(Firewall $firewall, Cleaner $cleaner, Comparator $comparator, Merger $merger, $tempPath)
    {
        $this->firewall = $firewall;
        $this->cleaner = $cleaner;
        $this->comparator = $comparator;
        $this->merger = $merger;
        $this->tempPath = $tempPath;
    }

    public function updateImage(Agenda $agenda, $newURL) {
        if($agenda->getPath() === null || ($newURL !== null && $agenda->getUrl() !== $newURL)) {
            $agenda->setUrl($newURL);
            $this->downloadImage($agenda);
        }
    }

    public function downloadImage(Agenda $agenda)
    {
        //$url = preg_replace('/([^:])(\/{2,})/', '$1/', $agenda->getUrl());
        $url = $agenda->getUrl();
        $path = $agenda->getPath();
        $agenda->setUrl(null)->setPath(null);

        if(! $url) {
            $agenda->setPath($path);
            return;
        }

        try {
            $image = file_get_contents($url);
        } catch (\Exception $ex) {
            $image = null;
        }

        if ($image) {
            $agenda->setUrl($url);

            //En cas d'url du type:  http://u.rl/image.png?params
            $ext = preg_replace("/(\?|_)(.*)$/", "", pathinfo($url, PATHINFO_EXTENSION));

            $filename = sha1(uniqid(mt_rand(), true)) . "." . $ext;

            $tempPath = $this->tempPath.'/'.$filename;
            $octets = file_put_contents($tempPath, $image);

            if ($octets > 0) {
                $file = new UploadedFile($tempPath, $filename, null, null, false, true);
                $agenda->setPath($filename);
                $agenda->setFile($file);

            }
        }
    }

    public function cleanPlace(Place $place) {
        $this->cleaner->cleanPlace($place);
    }

    public function cleanEvent(Agenda $event) {
        $this->cleaner->cleanEvent($event);
        if($event->getPlace()) {
            $this->cleaner->cleanPlace($event->getPlace());
        }
    }

    /**
     * @param array $persistedEvents
     * @param array $persistedPlaces
     * @param Agenda $event
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
