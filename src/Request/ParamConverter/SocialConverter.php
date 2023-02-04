<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Request\ParamConverter;

use App\Social\Social;
use App\Social\SocialProvider;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SocialConverter implements ValueResolverInterface
{
    public function __construct(private readonly SocialProvider $socialProvider)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }

        $argumentType = $argument->getType();
        if (Social::class !== $argumentType) {
            return [];
        }

        $service = $request->attributes->get('service');
        if (null === $service && !$argument->isNullable()) {
            throw new InvalidArgumentException('Route attribute is missing');
        }

        $options = [
            'default_facebook_name' => SocialProvider::FACEBOOK,
        ];

        $entity = $this->socialProvider->getSocial($service, $options['default_facebook_name']);

        return [$entity];
    }
}
