<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\DataTransformer;

use App\Dto\TagDto;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Transforms between TagDto and string representation for single tag fields.
 *
 * @implements DataTransformerInterface<TagDto|null, string|null>
 */
final class TagDtoTransformer implements DataTransformerInterface
{
    /**
     * Transforms a TagDto to a string (for display in form).
     */
    public function transform(mixed $value): ?string
    {
        return $value instanceof TagDto ? $value->name : null;
    }

    /**
     * Transforms a string to a TagDto (from form submission).
     */
    public function reverseTransform(mixed $value): ?TagDto
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        return TagDto::fromString((string) $value);
    }
}
