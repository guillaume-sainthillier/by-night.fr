<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Event;
use DateTimeImmutable;
use Vich\UploaderBundle\Handler\UploadHandler;

/**
 * Takes an event's images down (content removal): the files, their thumbnails and CDN copies
 * (ImageSubscriber, on Vich's removal), and the source's image for good.
 *
 * Emptying the file properties, as the takedown action did, removes nothing: Vich only deletes a
 * file along with its entity or when a new one replaces it, so the image stayed online, and an
 * image downloaded from a source would have come back with the next download anyway.
 */
final readonly class EventImageRemover
{
    public function __construct(private UploadHandler $uploadHandler)
    {
    }

    public function remove(Event $event): void
    {
        // Deletes the file and empties its name, size and type
        $this->uploadHandler->remove($event, 'imageFile');
        $this->uploadHandler->remove($event, 'imageSystemFile');

        $event
            ->setImageHash(null)
            ->setImageSystemHash(null)
            ->setImageRemovedAt(new DateTimeImmutable());
    }
}
