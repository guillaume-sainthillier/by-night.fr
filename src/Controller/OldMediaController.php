<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Twig\AssetExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Storage\StorageInterface;

class OldMediaController extends AbstractController
{
    /**
     * @Route("/media/cache/{filter}/{path<%patterns.path%>}", methods={"GET"})
     * @Route("/uploads/{path<%patterns.path%>}", methods={"GET"})
     * @ReverseProxy(expires="1 year")
     */
    public function index(string $path, StorageInterface $storage, AssetExtension $assetExtension, EventRepository $eventRepository, UserRepository $userRepository): Response
    {
        $infos = pathinfo($path);

        /** @var Event $event */
        $event = $eventRepository
            ->createQueryBuilder('e')
            ->where('e.image.name = :path OR e.imageSystem.name = :path')
            ->setParameter('path', $infos['basename'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($event) {
            if ($event->getImage()->getName() === $infos['basename']) {
                $url = $assetExtension->thumb($storage->resolvePath($event, 'imageFile'));
            } else {
                $url = $assetExtension->thumb($storage->resolvePath($event, 'imageSystemFile'));
            }

            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }

        /** @var User $user */
        $user = $userRepository
            ->createQueryBuilder('u')
            ->where('u.image.name = :path OR u.imageSystem.name = :path')
            ->setParameter('path', $infos['basename'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($user) {
            if ($user->getImage()->getName() === $infos['basename']) {
                $url = $assetExtension->thumb($storage->resolvePath($user, 'imageFile'));
            } else {
                $url = $assetExtension->thumb($storage->resolvePath($user, 'imageSystemFile'));
            }

            return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        }

        throw $this->createNotFoundException(sprintf('Unable to find event or user for path "%s"', $path));
    }
}
