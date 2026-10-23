<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * The organizer pages of the personal space a link without a publisher leads to, once the member's publisher is
 * known (App\Controller\PersonalSpace\PublisherController::entry()), or created (?suite=).
 */
enum PublisherSpacePage: string
{
    case Events = 'evenements';
    case NewEvent = 'nouvel-evenement';
    case Imports = 'imports';
    case Exports = 'exports';
    case Page = 'page';

    public function getRoute(): string
    {
        return match ($this) {
            self::Events => 'app_event_list',
            self::NewEvent => 'app_event_new',
            self::Imports => 'app_event_imports',
            self::Exports => 'app_event_exports',
            self::Page => 'app_publisher_edit',
        };
    }
}
