<?php

namespace AppBundle\Controller;

use AppBundle\Entity\City;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TBNController extends Controller
{
    protected function getSecondsUntilTomorrow()
    {
        $minuit = strtotime('tomorrow 00:00:00');

        return $minuit - time();
    }

    /**
     * @param City $city
     * @param $slug
     * @param $id
     * @param string $routeName
     * @param array  $extraParams
     *
     * @return null|object|RedirectResponse|\AppBundle\Entity\Agenda
     */
    protected function checkEventUrl(City $city, $slug, $id, $routeName = 'tbn_agenda_details', array $extraParams = [])
    {
        $em       = $this->getDoctrine()->getManager();
        $repoUser = $em->getRepository('AppBundle:Agenda');

        if (!$id) {
            $event = $repoUser->findOneBy(['slug' => $slug]);
        } else {
            $event = $repoUser->find($id);
        }

        if (!$event || !$event->getSlug()) {
            throw new NotFoundHttpException('Event not found');
        }

        $requestStack = $this->get('request_stack');
        if ($requestStack->getParentRequest() === null && (!$id || $event->getSlug() !== $slug)) {
            $routeParams = array_merge([
                'id'   => $event->getId(),
                'slug' => $event->getSlug(),
                'city' => $city->getSlug(),
            ], $extraParams);

            return new RedirectResponse($this->generateUrl($routeName, $routeParams));
        }

        return $event;
    }

    protected function getSecondsUntil($hours)
    {
        $time     = time();
        $now      = new \DateTime();
        $minutes  = $now->format('i');
        $secondes = $now->format('s');

        $string = $hours == 1 ? '+1 hour' : sprintf('+%d hours', $hours);
        $now->modify($string);

        if ($minutes > 0) {
            $now->modify('-' . $minutes . ' minutes');
        }

        if ($secondes > 0) {
            $now->modify('-' . $secondes . ' seconds');
        }

        return [$now, $now->getTimestamp() - $time];
    }

    protected function getRepo($name)
    {
        $em = $this->getDoctrine()->getManager();

        return $em->getRepository($name);
    }
}
