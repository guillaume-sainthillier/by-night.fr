<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Invalidator\TagsInvalidator;
use RuntimeException;
use Twig\Attribute\AsTwigFunction;

final class TagsExtension
{
    #[AsTwigFunction(name: 'tags')]
    public function getTags(string $type, mixed $object = null): string
    {
        return match ($type) {
            'location' => TagsInvalidator::getLocationTag($object),
            'event' => TagsInvalidator::getEventTag($object),
            'place' => TagsInvalidator::getPlaceTag($object),
            'user' => TagsInvalidator::getUserTag($object),
            'trends' => TagsInvalidator::getTrendTag($object),
            'header' => TagsInvalidator::getHeaderTag(),
            default => throw new RuntimeException(\sprintf('No tags for %s', $type)),
        };
    }
}
