<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Sheet;

use RuntimeException;

/**
 * A spreadsheet the import cannot read as a whole (no header, a required column missing, too many rows): its
 * message is for the member.
 */
final class InvalidSheetException extends RuntimeException
{
}
