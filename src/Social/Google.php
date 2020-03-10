<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

class Google extends Social
{
    public function constructClient()
    {
    }

    protected function getName()
    {
        return 'Google';
    }
}
