<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\Entity\Event;
use App\Entity\User;
use App\Helper\AssetHelper;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Vich\UploaderBundle\Storage\StorageInterface;

final class OldMediaController extends AbstractController
{
    #[Route(path: '/media/cache/{filter}/{path<%patterns.path%>}', methods: ['GET'])]
    #[Route(path: '/uploads/{path<%patterns.path%>}', methods: ['GET'])]
    #[ReverseProxy(expires: '1 year')]
    public function index(string $path, StorageInterface $storage, AssetHelper $assetHelper, EventRepository $eventRepository, UserRepository $userRepository): Response
    {
        $infos = pathinfo($path);
        /** @var Event|null $event */
        $event = $eventRepository
            ->createQueryBuilder('e')
            ->where('e.image.name = :path OR e.imageSystem.name = :path')
            ->setParameter('path', $infos['basename'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($event) {
            if ($event->getImage()->getName() === $infos['basename']) {
                $url = $assetHelper->getThumbS3Url($storage->resolvePath($event, 'imageFile'));
            } else {
                $url = $assetHelper->getThumbS3Url($storage->resolvePath($event, 'imageSystemFile'));
            }

            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }

        /** @var User|null $user */
        $user = $userRepository
            ->createQueryBuilder('u')
            ->where('u.image.name = :path OR u.imageSystem.name = :path')
            ->setParameter('path', $infos['basename'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($user) {
            if ($user->getImage()->getName() === $infos['basename']) {
                $url = $assetHelper->getThumbS3Url($storage->resolvePath($user, 'imageFile'));
            } else {
                $url = $assetHelper->getThumbS3Url($storage->resolvePath($user, 'imageSystemFile'));
            }

            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }

        throw $this->createNotFoundException(\sprintf('Unable to find event or user for path "%s"', $path));
    }
}
