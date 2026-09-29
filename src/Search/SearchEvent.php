<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Search;

use App\App\Location;
use App\Enum\AgendaType;
use App\Enum\DateRangePreset;
use App\Enum\PricePreset;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchEvent
{
    /** The widest radius around a city, in km: the last stop of the agenda's radius slider */
    public const int MAX_RANGE = 100;

    /** A date shortcut ("?when=this_weekend"), which the dates picked override */
    private ?DateRangePreset $when = null;

    /** The dates picked ("?dateRange[from]=…&dateRange[to]=…"), with no end or none at all */
    private ?DateTimeInterface $from = null;

    private ?DateTimeInterface $to = null;

    /** A price shortcut ("?price=under_20"), none for every price, the unknown ones included */
    private ?PricePreset $price = null;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    #[Assert\LessThanOrEqual(self::MAX_RANGE)]
    private ?int $range = 25;

    /**
     * @deprecated Use tagId instead for new Tag entity filtering
     */
    private ?string $tag = null;

    private ?int $tagId = null;

    private array $lieux = [];

    private ?string $term = null;

    /** The kind of outing of an agenda type page, which the keywords narrow down */
    private ?AgendaType $type = null;

    private ?Location $location = null;

    /**
     * @return string[]
     *
     * @psalm-return array<int, string>
     */
    public function getTerms(): array
    {
        return array_unique(array_filter(explode(' ', (string) $this->getTerm())));
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): self
    {
        $this->term = $term;

        return $this;
    }

    public function getType(): ?AgendaType
    {
        return $this->type;
    }

    public function setType(?AgendaType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getWhen(): ?DateRangePreset
    {
        return $this->when;
    }

    public function setWhen(?DateRangePreset $when): self
    {
        $this->when = $when;

        return $this;
    }

    /**
     * The date shortcut the search is on: the one asked, else "Tous les jours"; none for dates picked, which win.
     */
    public function getPreset(): ?DateRangePreset
    {
        return null === $this->from ? $this->when ?? DateRangePreset::Anytime : null;
    }

    /**
     * The days searched: those picked, else those of the shortcut.
     */
    public function getDateRange(): DateRange
    {
        if (null !== $this->from) {
            return new DateRange($this->from, $this->to);
        }

        return ($this->when ?? DateRangePreset::Anytime)->range();
    }

    public function getPrice(): ?PricePreset
    {
        return $this->price;
    }

    public function setPrice(?PricePreset $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getFrom(): ?DateTimeInterface
    {
        return $this->from;
    }

    public function setFrom(?DateTimeInterface $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getTo(): ?DateTimeInterface
    {
        return $this->to;
    }

    public function setTo(?DateTimeInterface $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function getTagId(): ?int
    {
        return $this->tagId;
    }

    public function setTagId(?int $tagId): self
    {
        $this->tagId = $tagId;

        return $this;
    }

    public function getLieux(): array
    {
        return $this->lieux;
    }

    public function setLieux(array $lieux): self
    {
        $this->lieux = $lieux;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getRange(): ?int
    {
        return $this->range;
    }

    public function setRange(?int $range): self
    {
        $this->range = $range;

        return $this;
    }
}
