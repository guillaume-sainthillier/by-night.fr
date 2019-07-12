<?php

namespace App\Search;

use App\App\Location;
use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class SearchEvent
{
    /**
     * @var DateTime|null
     * @Assert\Date
     */
    protected $du;

    /**
     * @var DateTime|null
     * @Assert\Date
     */
    protected $au;

    /**
     * @var int
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
     * @var int
     */
    protected $page;

    /**
     * @var Location|null
     */
    protected $location;

    public function __construct()
    {
        $this->page = 1;
        $this->range = 25;
        $this->du = new DateTime();
        $this->lieux = [];
        $this->type_manifestation = [];
    }

    public function getTerms()
    {
        return \array_unique(\array_filter(\explode(' ', $this->getTerm())));
    }

    public function getDu(): ?DateTime
    {
        return $this->du;
    }

    public function setDu(?DateTime $du): SearchEvent
    {
        $this->du = $du;

        return $this;
    }

    public function getAu(): ?DateTime
    {
        return $this->au;
    }

    public function setAu(?DateTime $au): SearchEvent
    {
        $this->au = $au;

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

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): SearchEvent
    {
        $this->term = $term;

        return $this;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): SearchEvent
    {
        $this->page = $page;

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

    public function getRange(): int
    {
        return $this->range;
    }

    public function setRange(int $range): SearchEvent
    {
        $this->range = $range;
        return $this;
    }
}
