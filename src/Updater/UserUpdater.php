<?php

namespace App\Updater;

use App\Entity\User;
use App\Handler\UserHandler;
use App\Social\FacebookAdmin;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;

class UserUpdater extends Updater
{
    const PAGINATION_SIZE = 50;

    /**
     * @var UserHandler
     */
    protected $userHandler;

    public function __construct(EntityManagerInterface $entityManager, FacebookAdmin $facebookAdmin, UserHandler $userHandler)
    {
        parent::__construct($entityManager, $facebookAdmin);
        $this->userHandler = $userHandler;
    }

    public function update(\DateTimeInterface $from)
    {
        $repo = $this->entityManager->getRepository(User::class);
        $count = $repo->getUserFbIdsCount($from);

        $nbBatchs = \ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        for ($i = 1; $i <= $nbBatchs; $i++) {
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
        return array_filter(array_unique(array_map(function (User $user) {
            return $user->getInfo()->getFacebookId();
        }, $users)));
    }

    protected function doUpdate(array $users, array $downloadUrls)
    {
        $responses = $this->downloadUrls($downloadUrls);
        foreach ($users as $user) {
            /** @var User $user */
            if (empty($responses[$user->getInfo()->getFacebookId()])) {
                continue;
            }

            $response = $responses[$user->getInfo()->getFacebookId()];
            if ($this->userHandler->hasToUploadNewImage($response['content'], $user)) {
                $this->userHandler->uploadFile($user, $response['content'], $response['contentType']);
            }
        }
    }

    protected function doFlush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear(User::class);
    }
}
