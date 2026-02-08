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
use App\Utils\PaginateTrait;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UserUpdater extends Updater
{
    use PaginateTrait;

    private const int PAGINATION_SIZE = 50;

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
        $paginator = $this->createQueryBuilderPaginator(
            $this->userRepository->getUsersWithInfoQueryBuilder($from),
            1,
            self::PAGINATION_SIZE
        );

        $count = $paginator->getNbResults();
        $nbBatchs = (int) ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        for ($i = 1; $i <= $nbBatchs; ++$i) {
            $paginator->setCurrentPage($i);
            $users = iterator_to_array($paginator->getCurrentPageResults());
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
        /** @var array<int, string> $tempFilePaths */
        $tempFilePaths = [];

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
                /** @var User $user */
                $user = $response->getInfo('user_data');
                $userId = $user->getId();

                if ($chunk->isTimeout()) {
                    $response->cancel();
                    unset($tempFilePaths[$userId]);
                } elseif ($chunk->isFirst()) {
                    if (200 !== $response->getStatusCode()) {
                        $response->cancel();
                    } else {
                        $tempFilePaths[$userId] = $this->userHandler->createTempFile();
                    }
                } elseif (isset($tempFilePaths[$userId])) {
                    file_put_contents($tempFilePaths[$userId], $chunk->getContent(), \FILE_APPEND);

                    if ($chunk->isLast()) {
                        if ($this->userHandler->hasToUploadNewImage($tempFilePaths[$userId], $user)) {
                            $this->userHandler->uploadFile($user, $tempFilePaths[$userId]);
                        }
                        unset($tempFilePaths[$userId]);
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
        $this->entityManager->clear();
    }
}
