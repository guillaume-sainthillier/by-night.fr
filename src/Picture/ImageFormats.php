<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

/**
 * The image formats stored, uploaded by members or downloaded from the sources: the raster
 * formats the thumbnails are made from (GD reads them all). Not "image/*": an SVG is an XML
 * document that may carry script, which runs when the file is opened from the storage domain.
 */
final class ImageFormats
{
    /** The file extension of each format */
    public const array EXTENSIONS = [
        'image/jpeg' => 'jpeg',
        'image/jpg' => 'jpeg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public const array MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    /**
     * The weight of an image, in bytes ("6M" for the File constraint). The forms also give it to their file field
     * (data-max-size): the browser refuses a heavier one before sending the form, since a request over PHP's
     * post_max_size reaches the form empty and the member loses everything filled in.
     */
    public const int MAX_SIZE = 6_000_000;

    public static function getExtension(?string $mimeType): ?string
    {
        return self::EXTENSIONS[$mimeType] ?? null;
    }
}
