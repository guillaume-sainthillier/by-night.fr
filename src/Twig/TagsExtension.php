<?php

namespace App\Twig;

use App\Invalidator\TagsInvalidator;
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
    }
}
