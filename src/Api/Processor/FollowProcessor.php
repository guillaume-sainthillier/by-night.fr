<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\FollowInput;
use App\Api\Model\FollowOutput;
use App\Entity\Place;
use App\Entity\Publisher;
use App\Entity\User;
use App\Follow\FollowManager;
use App\Repository\FollowRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The "Suivre" button of a publisher's page and of a venue's agenda.
 *
 * @implements ProcessorInterface<FollowInput, FollowOutput>
 */
final readonly class FollowProcessor implements ProcessorInterface
{
    public function __construct(
        private FollowManager $followManager,
        private FollowRepository $followRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FollowOutput
    {
        $target = $context['request']->attributes->get('read_data');
        if (!$target instanceof Publisher && !$target instanceof Place) {
            throw new NotFoundHttpException('Not Found');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $this->followManager->follow($user, $target, $data->following);

        return new FollowOutput(
            following: $data->following,
            followers: $this->followRepository->countFollowers($target),
        );
    }
}
