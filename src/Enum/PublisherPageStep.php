<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

use App\Entity\Publisher;
use App\Stats\PublisherStats;

/**
 * What makes a publisher's page complete (App\Stats\PublisherCompletion): its owner sees what is left, each step worth
 * as much as the others.
 */
enum PublisherPageStep: string
{
    case Logo = 'logo';
    case Description = 'description';
    case Links = 'links';
    case Cover = 'cover';
    case UpcomingEvent = 'upcoming_event';

    public function isDoneBy(Publisher $publisher, PublisherStats $stats): bool
    {
        return match ($this) {
            self::Logo => $publisher->hasLogo(),
            self::Description => null !== $publisher->getDescription(),
            self::Links => null !== $publisher->getWebsite() || [] !== $publisher->getSocialLinks(),
            self::Cover => $publisher->hasCover(),
            self::UpcomingEvent => $stats->upcomingEvents > 0,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Logo => 'Un logo',
            self::Description => 'Une présentation',
            self::Links => 'Votre site ou vos réseaux',
            self::Cover => 'Une image de couverture',
            self::UpcomingEvent => 'Un événement à venir',
        };
    }

    public function getHint(): string
    {
        return match ($this) {
            self::Logo => 'Il accompagne vos événements, sur leur page et dans les listes.',
            self::Description => 'Quelques lignes sur ce que vous proposez donnent envie de vous suivre.',
            self::Links => 'Les visiteurs vous retrouvent depuis chacun de vos événements.',
            self::Cover => 'Elle habille le haut de votre page.',
            self::UpcomingEvent => 'Une page avec une date à venir est mise en avant dans les moteurs de recherche.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Logo => 'lucide:image',
            self::Description => 'lucide:text',
            self::Links => 'lucide:link',
            self::Cover => 'lucide:rectangle-horizontal',
            self::UpcomingEvent => 'lucide:calendar-plus',
        };
    }
}
