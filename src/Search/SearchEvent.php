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
use DateTime;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchEvent
{
    #[Assert\NotBlank]
    private ?DateTimeInterface $from;

    private ?DateTimeInterface $to = null;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    private ?int $range = 25;

    private ?string $tag = null;

    protected array $type = [];

    protected array $lieux = [];

    private ?string $term = null;

    private ?Location $location = null;

    public function __construct()
    {
        $this->from = new DateTime();
    }

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

    public function getType(): array
    {
        return $this->type;
    }

    public function setType(array $type): self
    {
        $this->type = $type;

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
