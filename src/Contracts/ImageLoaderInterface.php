<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface ImageLoaderInterface
{
    public function getUrl(array $params): string;

    public function supports(array $params): bool;

    public function getDefaultParams(array $params): array;
}
