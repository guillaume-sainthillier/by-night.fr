<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Dto\EventDto;
use LogicException;
use Override;

final class DigitickAwinParser extends AbstractAwinParser
{
    #[Override]
    public function isEnabled(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'SeeTickets (ex Digitick)';
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'awin.digitick';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAwinUrl(): string
    {
        throw new LogicException('this parser is not used anymore');
    }

    /**
     * {@inheritDoc}
     */
    protected function arrayToDto(array $data): ?EventDto
    {
        throw new LogicException('this parser is not used anymore');
    }
}
