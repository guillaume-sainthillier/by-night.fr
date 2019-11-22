<?php

namespace App\Search;

use App\App\Location;
use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class SearchEvent
{
    /**
     * @var DateTime|null
     * @Assert\NotBlank
     * @Assert\Date
     */
    protected $from;

    /**
     * @var DateTime|null
     * @Assert\Date
     */
    protected $to;

    /**
     * @var int
     * @Assert\NotBlank
     * @Assert\GreaterThanOrEqual(0)
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

    public function setTerm(?string $term): SearchEvent
    {
        $this->term = $term;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getFrom(): ?DateTime
    {
        return $this->from;
    }

    /**
     * @param DateTime|null $from
     * @return SearchEvent
     */
    public function setFrom(?DateTime $from): SearchEvent
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getTo(): ?DateTime
    {
        return $this->to;
    }

    /**
     * @param DateTime|null $to
     * @return SearchEvent
     */
    public function setTo(?DateTime $to): SearchEvent
    {
        $this->to = $to;
        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): SearchEvent
    {
        $this->tag = $tag;

        return $this;
    }

    public function getTypeManifestation(): array
    {
        return $this->type_manifestation;
    }

    public function setTypeManifestation(array $type_manifestation): SearchEvent
    {
        $this->type_manifestation = $type_manifestation;

        return $this;
    }

    public function getLieux(): array
    {
        return $this->lieux;
    }

    public function setLieux(array $lieux): SearchEvent
    {
        $this->lieux = $lieux;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): SearchEvent
    {
        $this->location = $location;

        return $this;
    }

    public function getRange(): ?int
    {
        return $this->range;
    }

    public function setRange(?int $range): SearchEvent
    {
        $this->range = $range;
        return $this;
    }
}
