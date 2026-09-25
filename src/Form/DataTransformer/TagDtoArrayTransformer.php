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
 * Transforms between TagDto array and comma-separated string representation for multi-tag fields.
 *
 * @implements DataTransformerInterface<TagDto[], string|null>
 */
final class TagDtoArrayTransformer implements DataTransformerInterface
{
    /**
     * Stands for a comma inside a name, which the field and its tags widget would take for two
     * themes: DataTourisme names some themes so ("Peintures, arts graphiques"). A single low-9
     * quotation mark looks the same and nobody types it.
     */
    private const string COMMA_IN_NAME = "\u{201A}";

    /**
     * Transforms a TagDto array to a comma-separated string (for display in form).
     */
    public function transform(mixed $value): ?string
    {
        if (!\is_array($value)) {
            return null;
        }

        $names = array_filter(array_map(
            static fn (TagDto $dto) => null === $dto->name ? null : str_replace(',', self::COMMA_IN_NAME, $dto->name),
            $value
        ));

        if ([] === $names) {
            return null;
        }

        return implode(',', $names);
    }

    /**
     * Transforms a comma-separated string to a TagDto array (from form submission).
     *
     * @return TagDto[]
     */
    public function reverseTransform(mixed $value): array
    {
        if (null === $value || '' === trim((string) $value)) {
            return [];
        }

        $names = array_filter(array_map(
            static fn (string $name): string => trim(str_replace(self::COMMA_IN_NAME, ',', $name)),
            explode(',', (string) $value)
        ));

        return array_map(
            TagDto::fromString(...),
            $names
        );
    }
}
