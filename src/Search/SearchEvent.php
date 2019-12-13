<?php

namespace App\Search;

use App\App\Location;
use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class SearchEvent
{
    /**
     * @var \DateTimeInterface|null
     * @Assert\NotBlank
     * @Assert\Date
     */
    protected $from;

    /**
     * @var \DateTimeInterface|null
     * @Assert\Date
     */
    protected $to;

    /**
     * @var int
     * @Assert\NotBlank
     * @Assert\GreaterThan(0)
     */
    protected $range;

    /**
     * @var string|null
     */
    protected $tag;

    /**
     * @var array
     */
    protected $type_manifestation;

    /**
     * @var array
     */
    protected $lieux;

    /**
     * @var string|null
     */
    protected $term;

    /**
     * @var Location|null
     */
    protected $location;

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
    public function getFrom(): ?\DateTimeInterface
    {
        return $this->from;
    }

    /**
     * @param DateTime|null $from
     */
    public function setFrom(?\DateTimeInterface $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getTo(): ?\DateTimeInterface
    {
        return $this->to;
    }

    public function setTo(?\DateTimeInterface $to): self
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
