<?php

namespace App\Controller;

use App\Entity\Agenda;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class TBNController extends AbstractController
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    protected function getSecondsUntilTomorrow()
    {
        $minuit = \strtotime('tomorrow 00:00:00');

        return $minuit - \time();
    }

    protected function checkEventUrl($locationSlug, $eventSlug, $eventId, $routeName = 'app_agenda_details', array $extraParams = [])
    {
        $em = $this->getDoctrine()->getManager();
        $repoEvent = $em->getRepository(Agenda::class);

        if (!$eventId) {
            $event = $repoEvent->findOneBy(['slug' => $eventSlug]);
        } else {
            $event = $repoEvent->find($eventId);
        }

        if (!$event || !$event->getSlug()) {
            throw $this->createNotFoundException('Event not found');
        }

        if (null === $this->requestStack->getParentRequest() && (
                !$eventId
                || $event->getSlug() !== $eventSlug
                || $event->getLocationSlug() !== $locationSlug
            )) {
            $routeParams = \array_merge([
                'id' => $event->getId(),
                'slug' => $event->getSlug(),
                'location' => $event->getLocationSlug(),
            ], $extraParams);

            return $this->redirectToRoute($routeName, $routeParams, Response::HTTP_MOVED_PERMANENTLY);
        }

        return $event;
    }

    protected function getSecondsUntil($hours)
    {
        $time = \time();
        $now = new DateTime();
        $minutes = $now->format('i');
        $secondes = $now->format('s');

        $string = 1 == $hours ? '+1 hour' : \sprintf('+%d hours', $hours);
        $now->modify($string);

        if ($minutes > 0) {
            $now->modify('-' . $minutes . ' minutes');
        }

        if ($secondes > 0) {
            $now->modify('-' . $secondes . ' seconds');
        }

        return [$now, $now->getTimestamp() - $time];
    }
}
