<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

final class Google extends Social
{
    /**
     * {@inheritDoc}
     */
    protected function constructClient(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getInfoPropertyPrefix(): string
    {
        return 'google';
    }

    /**
     * {@inheritDoc}
     */
    protected function getRoleName(): string
    {
        return 'ROLE_GOOGLE';
    }
}
