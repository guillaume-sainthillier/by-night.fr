<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Utils\FrenchPreposition;
use Twig\Attribute\AsTwigFilter;

/**
 * "Sortir {{ place.name|at_place }}" → "Sortir au Bikini", "près {{ city.name|of_place }}" → "près d'Albi".
 */
final class FrenchExtension
{
    #[AsTwigFilter(name: 'at_place')]
    public function atPlace(?string $name): string
    {
        return null === $name ? '' : FrenchPreposition::at($name);
    }

    #[AsTwigFilter(name: 'of_place')]
    public function ofPlace(?string $name): string
    {
        return null === $name ? '' : FrenchPreposition::of($name);
    }
}
