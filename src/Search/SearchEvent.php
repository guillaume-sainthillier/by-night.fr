<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Search;

use App\App\Location;
use DateTime;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SearchEvent
{
    /**
     * @Assert\NotBlank
     * @Assert\Date
     */
    protected ?DateTimeInterface $from = null;

    /**
     * @Assert\Date
     */
    protected ?DateTimeInterface $to = null;

    /**
     * @Assert\NotBlank
     * @Assert\GreaterThan(0)
     */
    protected ?int $range = null;

    protected ?string $tag = null;

    protected array $type_manifestation;

    protected array $lieux;

    protected ?string $term = null;

    protected ?Location $location = null;

    public function __construct()
    {
        $this->range = 25;
        $this->from = new DateTime();
        $this->lieux = [];
        $this->type_manifestation = [];
    }

    public function getTerms()
    {
        return \array_unique(\array_filter(\explode(' ', $this->getTerm())));
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

    /**
     * @return DateTime|null
     */
    public function getFrom(): ?DateTimeInterface
    {
        return $this->from;
    }

    /**
     * @param DateTime|null $from
     */
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

    public function getTypeManifestation(): array
    {
        return $this->type_manifestation;
    }

    public function setTypeManifestation(array $type_manifestation): self
    {
        $this->type_manifestation = $type_manifestation;

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
