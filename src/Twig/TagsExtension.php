<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Invalidator\TagsInvalidator;
use RuntimeException;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class TagsExtension extends Extension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('tags', [$this, 'getTags']),
        ];
    }

    public function getTags($type, $object = null)
    {
        switch ($type) {
            case 'location':
                return TagsInvalidator::getLocationTag($object);
            case 'event':
                return TagsInvalidator::getEventTag($object);
            case 'place':
                return TagsInvalidator::getPlaceTag($object);
            case 'user':
                return TagsInvalidator::getUserTag($object);
            case 'tendances':
                return TagsInvalidator::getTendanceTag($object);
            case 'menu':
                return TagsInvalidator::getMenuTag();
        }

        throw new RuntimeException(sprintf('No tags for %s', $type));
    }
}
