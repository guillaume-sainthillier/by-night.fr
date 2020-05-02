<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Updater;

use App\Entity\User;
use App\Handler\UserHandler;
use App\Repository\UserRepository;
use App\Social\FacebookAdmin;
use App\Utils\Monitor;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class UserUpdater extends Updater
{
    private const PAGINATION_SIZE = 50;

    protected UserHandler $userHandler;
    /**
     * @var \App\Repository\UserRepository
     */
    private $userRepository;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, FacebookAdmin $facebookAdmin, UserHandler $userHandler, UserRepository $userRepository)
    {
        parent::__construct($entityManager, $logger, $facebookAdmin);

        $this->userHandler = $userHandler;
        $this->userRepository = $userRepository;
    }

    public function update(DateTimeInterface $from)
    {
        $repo = $this->userRepository;
        $count = $repo->getUserFbIdsCount($from);

        $nbBatchs = \ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        for ($i = 1; $i <= $nbBatchs; ++$i) {
            $users = $repo->getUsersWithInfo($from, $i, self::PAGINATION_SIZE);
            $fbIds = $this->extractFbIds($users);
            $fbStats = $this->facebookAdmin->getUserImagesFromIds($fbIds);

            $this->doUpdate($users, $fbStats);
            $this->doFlush();
            Monitor::advanceProgressBar();
        }
    }

    private function extractFbIds(array $users)
    {
        return array_filter(array_unique(array_map(fn (User $user) => $user->getInfo()->getFacebookId(), $users)));
    }

    /**
     * @param User[] $users
     */
    private function doUpdate(array $users, array $downloadUrls)
    {
        $responses = [];

        foreach ($users as $user) {
            if (empty($downloadUrls[$user->getInfo()->getFacebookId()])) {
                continue;
            }

            $uri = $downloadUrls[$user->getInfo()->getFacebookId()];
            $responses[] = $this->client->request('GET', $uri, [
                'user_data' => $user,
            ]);
        }

        foreach ($this->client->stream($responses) as $response => $chunk) {
            try {
                if ($chunk->isFirst() && 200 !== $response->getStatusCode()) {
                    continue;
                } elseif ($chunk->isLast()) {
                    $content = $response->getContent();
                    $user = $response->getInfo('user_data');

                    if ($this->userHandler->hasToUploadNewImage($content, $user)) {
                        $contentType = $response->getHeaders()['content-type'][0];
                        dump($response->getInfo('url'), $response->getInfo('user_data')->getInfo()->getFacebookId(), $contentType);
                        $this->userHandler->uploadFile($user, $content, $contentType);
                    }
                }
            } catch (HttpExceptionInterface $e) {
                $infos = $response->getInfo();
                unset($infos['user_data']);
            }
        }
    }

    private function doFlush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear(User::class);
    }
}
