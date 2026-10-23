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
 * The news By Night sends the publishers (app:publishers:send-news), an edition each: its value names its templates,
 * templates/email/publisher-news/<value>.{mjml,txt}.twig, and is recorded with every delivery, so that no member gets
 * an edition twice. A new edition is a case and its two templates.
 */
enum PublisherNewsEdition: string
{
    /** What changed since spring 2026, for the publishers who stopped coming */
    case Comeback2026 = '2026-10-retour';

    case October2026 = '2026-10';

    public function getAudience(): PublisherNewsAudience
    {
        return match ($this) {
            self::Comeback2026 => PublisherNewsAudience::Dormant,
            self::October2026 => PublisherNewsAudience::Active,
        };
    }

    public function getSubject(): string
    {
        return match ($this) {
            self::Comeback2026 => 'Ce qui a changé sur By Night depuis votre dernière visite',
            self::October2026 => "Les nouveautés d'octobre pour les organisateurs - By Night",
        };
    }

    public function getTemplate(): string
    {
        return 'email/publisher-news/' . $this->value;
    }
}
