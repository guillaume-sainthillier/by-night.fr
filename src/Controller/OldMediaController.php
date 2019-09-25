<?php

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\Entity\Event;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class OldMediaController extends AbstractController
{
    /**
     * @Route("/media/cache/{filter}/{path}", requirements={"path"=".+"})
     * @ReverseProxy(expires="1 year")
     *
     * @return Response
     */
    public function indexAction(string $filter, string $path, CacheManager $cacheManager, UploaderHelper $helper, Packages $packages)
    {
        $infos = pathinfo($path);

        $eventRepository = $this->getDoctrine()->getRepository(Event::class);
        /** @var Event $event */
        $event = $eventRepository
            ->createQueryBuilder('e')
            ->where('e.systemPath = :path OR e.path = :path')
            ->setParameter('path', $infos['basename'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($event) {
            if ($event->getPath() === $infos['basename']) {
                $url = $cacheManager->getBrowserPath($helper->asset($event, 'file'), $filter);
            } else {
                $url = $cacheManager->getBrowserPath($helper->asset($event, 'systemFile'), $filter);
            }
            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }

        $userRepository = $this->getDoctrine()->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository
            ->createQueryBuilder('u')
            ->where('u.systemPath = :path OR u.path = :path')
            ->setParameter('path', $infos['basename'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($user) {
            if ($user->getPath() === $infos['basename']) {
                $url = $cacheManager->getBrowserPath($helper->asset($user, 'imageFile'), $filter);
            } else {
                $url = $cacheManager->getBrowserPath($helper->asset($user, 'imageSystemFile'), $filter);
            }

            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }


        throw $this->createNotFoundException(
            sprintf('Unable to find event or user for path "%s"', $path)
        );
    }
}
