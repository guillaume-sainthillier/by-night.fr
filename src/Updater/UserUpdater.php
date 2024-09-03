<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserUpdater extends Updater
{
    /**
     * @var int
     */
    private const PAGINATION_SIZE = 50;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        FacebookAdmin $facebookAdmin,
        HttpClientInterface $client,
        protected UserHandler $userHandler,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct($entityManager, $logger, $facebookAdmin, $client);
    }

    /**
     * {@inheritDoc}
     */
    public function update(DateTimeInterface $from): void
    {
        $repo = $this->userRepository;
        $count = $repo->getUserFbIdsCount($from);

        $nbBatchs = ceil($count / self::PAGINATION_SIZE);
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

    /**
     * @return string[]
     *
     * @psalm-return array<string>
     */
    private function extractFbIds(array $users): array
    {
        return array_filter(array_unique(array_map(static fn (User $user) => $user->getOAuth()->getFacebookId(), $users)));
    }

    /**
     * @param User[] $users
     */
    private function doUpdate(array $users, array $downloadUrls): void
    {
        $responses = [];

        foreach ($users as $user) {
            if (empty($downloadUrls[$user->getOAuth()->getFacebookId()])) {
                continue;
            }

            $uri = $downloadUrls[$user->getOAuth()->getFacebookId()];
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
                        $this->userHandler->uploadFile($user, $content, $contentType);
                    }
                }
            } catch (HttpExceptionInterface $httpException) {
                $infos = $httpException->getResponse()->getInfo();
                unset($infos['user_data']);
            }
        }
    }

    private function doFlush(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear(User::class);
    }
}
