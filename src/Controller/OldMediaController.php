<?php

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\Entity\Event;
use App\Entity\User;
use App\Twig\AssetExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Storage\StorageInterface;

class OldMediaController extends AbstractController
{
    /**
     * @Route("/media/cache/{filter}/{path}", requirements={"path"=".+"})
     * @Route("/uploads/{path}", requirements={"path"=".+"})
     * @ReverseProxy(expires="1 year")
     *
     * @return Response
     */
    public function indexAction(string $path, StorageInterface $storage, AssetExtension $assetExtension)
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
                $url = $assetExtension->thumb($storage->resolvePath($event, 'file'));
            } else {
                $url = $assetExtension->thumb($storage->resolvePath($event, 'systemFile'));
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
                $url = $assetExtension->thumb($storage->resolvePath($user, 'imageFile'));
            } else {
                $url = $assetExtension->thumb($storage->resolvePath($user, 'imageSystemFile'));
            }

            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }


        throw $this->createNotFoundException(
            sprintf('Unable to find event or user for path "%s"', $path)
        );
    }
}
