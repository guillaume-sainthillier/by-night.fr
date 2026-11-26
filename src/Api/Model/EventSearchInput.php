<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use ApiPlatform\Metadata\ApiProperty;
use App\Enum\AgendaType;
use App\Enum\DateRangePreset;
use App\Enum\PricePreset;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What GET /api/events and the MCP tool search_events take: a city, then what the agenda's filters narrow it to.
 */
final readonly class EventSearchInput
{
    public function __construct(
        /** The city: its name ("Toulouse", "Saint-Étienne") or its slug from find_cities ("toulouse"). */
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $city,
        /** When, unless from is given. */
        public DateRangePreset $when = DateRangePreset::Anytime,
        #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date', 'description' => 'First day, YYYY-MM-DD: overrides when.'])]
        public ?DateTimeImmutable $from = null,
        #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date', 'description' => 'Last day, YYYY-MM-DD, with from.'])]
        #[Assert\GreaterThanOrEqual(propertyPath: 'from')]
        public ?DateTimeImmutable $to = null,
        /** A kind of event: concert, show (theatre, comedy, dance), exhibition, family or student (parties). */
        public ?AgendaType $category = null,
        /** Words to look for in French: an artist, a genre ("jazz"), a kind of outing ("marché de Noël"). */
        #[Assert\Length(max: 200)]
        public ?string $query = null,
        /** A price ceiling in euros: free, under_10, under_20 or under_50. */
        public ?PricePreset $price = null,
        /** How many events to list, 1 to 20. */
        #[Assert\Range(min: 1, max: 20)]
        public int $limit = 10,
    ) {
    }
}
