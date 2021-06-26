<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Request\ParamConverter;

use App\Social\Social;
use App\Social\SocialProvider;
use InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

class SocialConverter implements ParamConverterInterface
{
    private SocialProvider $socialProvider;

    public function __construct(SocialProvider $socialProvider)
    {
        $this->socialProvider = $socialProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $service = $request->attributes->get('service');

        if (null === $service && !$configuration->isOptional()) {
            throw new InvalidArgumentException('Route attribute is missing');
        }

        $options = array_merge([
            'default_facebook_name' => SocialProvider::FACEBOOK,
        ], $configuration->getOptions());

        $entity = $this->socialProvider->getSocial($service, $options['default_facebook_name']);
        $request->attributes->set($configuration->getName(), $entity);

        $configuration->setClass(null);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(ParamConverter $configuration)
    {
        return Social::class === $configuration->getClass();
    }
}
