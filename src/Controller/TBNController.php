<?php

namespace App\Controller;

use App\Entity\Agenda;
use App\Entity\City;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    /**
     * @param City $city
     * @param $slug
     * @param $id
     * @param string $routeName
     * @param array  $extraParams
     *
     * @return null|object|RedirectResponse|Agenda
     */
    protected function checkEventUrl(City $city, $slug, $id, $routeName = 'app_agenda_details', array $extraParams = [])
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

        if (null === $this->requestStack->getParentRequest() && (!$id || $event->getSlug() !== $slug || $event->getPlace()->getCity()->getSlug() !== $city->getSlug())) {
            $routeParams = \array_merge([
                'id'   => $event->getId(),
                'slug' => $event->getSlug(),
                'city' => $event->getPlace()->getCity()->getSlug(),
            ], $extraParams);

            return $this->redirectToRoute($routeName, $routeParams, Response::HTTP_MOVED_PERMANENTLY);
        }

        return $event;
    }

    protected function getSecondsUntil($hours)
    {
        $time     = \time();
        $now      = new DateTime();
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
