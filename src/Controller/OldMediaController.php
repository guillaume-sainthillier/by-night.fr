<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Vich\UploaderBundle\Storage\StorageInterface;

final class OldMediaController extends AbstractController
{
    /**
     * Bots keep requesting these legacy URLs: Cloudflare and browsers keep the redirect a month
     * (like PicassoOriginRedirectSubscriber) instead of asking the database again each time.
     * The target is the mapping's URI (/uploads/documents/…, /uploads/users/…), which mirrors
     * the S3 prefix of its storage: the bare storage path has no such prefix on the data host.
     */
    private const int REDIRECT_MAX_AGE = 2592000;

    #[Route(path: '/media/cache/{filter}/{path<%patterns.path%>}', methods: ['GET'])]
    #[Route(path: '/uploads/{path<%patterns.path%>}', methods: ['GET'])]
    public function index(string $path, StorageInterface $storage, Packages $packages, EventRepository $eventRepository, UserRepository $userRepository): Response
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
            $field = $event->getImage()->getName() === $infos['basename'] ? 'imageFile' : 'imageSystemFile';
            $url = $packages->getUrl($storage->resolveUri($event, $field), 's3');

            return $this->permanentRedirect($url);
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
            $field = $user->getImage()->getName() === $infos['basename'] ? 'imageFile' : 'imageSystemFile';
            $url = $packages->getUrl($storage->resolveUri($user, $field), 's3');

            return $this->permanentRedirect($url);
        }

        throw $this->createNotFoundException(\sprintf('Unable to find event or user for path "%s"', $path));
    }

    private function permanentRedirect(string $url): Response
    {
        $response = $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
        $response->setPublic();
        $response->setMaxAge(self::REDIRECT_MAX_AGE);

        return $response;
    }
}
