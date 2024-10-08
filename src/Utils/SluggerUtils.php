<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

final class SluggerUtils
{
    private static ?SluggerInterface $slugger = null;

    public function __construct(SluggerInterface $slugger)
    {
        self::$slugger = $slugger;
    }

    public static function generateSlug(string $content): string
    {
        return self::getSluggerInstance()->slug($content)->lower()->toString();
    }

    private static function getSluggerInstance(): SluggerInterface
    {
        if (null === self::$slugger) {
            self::$slugger = new AsciiSlugger();
        }

        return self::$slugger;
    }
}
