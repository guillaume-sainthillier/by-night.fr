<?php

namespace App\Controller;

use App\Entity\Agenda;
use App\Entity\City;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TBNController extends Controller
{
    protected function getSecondsUntilTomorrow()
    {
        $minuit = \strtotime('tomorrow 00:00:00');

        return $minuit - \time();
    }

    /**
     * @param City $city
     * @param $slug
     * @param $id
     * @param string $routeName
     * @param array  $extraParams
     *
     * @return null|object|RedirectResponse|\App\Entity\Agenda
     */
    protected function checkEventUrl(City $city, $slug, $id, $routeName = 'tbn_agenda_details', array $extraParams = [])
    {
        $em       = $this->getDoctrine()->getManager();
        $repoUser = $em->getRepository(Agenda::class);

        if (!$id) {
            $event = $repoUser->findOneBy(['slug' => $slug]);
        } else {
            $event = $repoUser->find($id);
        }

        if (!$event || !$event->getSlug()) {
            throw new NotFoundHttpException('Event not found');
        }

        $requestStack = $this->get(RequestStack::class);

        if (null === $requestStack->getParentRequest() && (!$id || $event->getSlug() !== $slug || $event->getPlace()->getCity()->getSlug() !== $city->getSlug())) {
            $routeParams = \array_merge([
                'id'   => $event->getId(),
                'slug' => $event->getSlug(),
                'city' => $event->getPlace()->getCity()->getSlug(),
            ], $extraParams);

            return new RedirectResponse($this->generateUrl($routeName, $routeParams));
        }

        return $event;
    }

    protected function getSecondsUntil($hours)
    {
        $time     = \time();
        $now      = new \DateTime();
        $minutes  = $now->format('i');
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
