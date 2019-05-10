<?php

namespace App\Search;

use App\App\Location;
use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class SearchEvent
{
    /**
     * @var DateTime|null
     * @Assert\Date()
     */
    protected $du;

    /**
     * @var DateTime|null
     * @Assert\Date()
     */
    protected $au;

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
        $this->du = new DateTime();
        $this->lieux = [];
        $this->type_manifestation = [];
    }

    public function getTerms()
    {
        return \array_unique(\array_filter(\explode(' ', $this->getTerm())));
    }

    /**
     * @return DateTime|null
     */
    public function getDu(): ?DateTime
    {
        return $this->du;
    }

    /**
     * @param DateTime|null $du
     * @return SearchEvent
     */
    public function setDu(?DateTime $du): SearchEvent
    {
        $this->du = $du;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getAu(): ?DateTime
    {
        return $this->au;
    }

    /**
     * @param DateTime|null $au
     * @return SearchEvent
     */
    public function setAu(?DateTime $au): SearchEvent
    {
        $this->au = $au;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * @param string|null $tag
     * @return SearchEvent
     */
    public function setTag(?string $tag): SearchEvent
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * @return array
     */
    public function getTypeManifestation(): array
    {
        return $this->type_manifestation;
    }

    /**
     * @param array $type_manifestation
     * @return SearchEvent
     */
    public function setTypeManifestation(array $type_manifestation): SearchEvent
    {
        $this->type_manifestation = $type_manifestation;
        return $this;
    }

    /**
     * @return array
     */
    public function getLieux(): array
    {
        return $this->lieux;
    }

    /**
     * @param array $lieux
     * @return SearchEvent
     */
    public function setLieux(array $lieux): SearchEvent
    {
        $this->lieux = $lieux;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTerm(): ?string
    {
        return $this->term;
    }

    /**
     * @param string|null $term
     * @return SearchEvent
     */
    public function setTerm(?string $term): SearchEvent
    {
        $this->term = $term;
        return $this;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param int $page
     * @return SearchEvent
     */
    public function setPage(int $page): SearchEvent
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return Location|null
     */
    public function getLocation(): ?Location
    {
        return $this->location;
    }

    /**
     * @param Location|null $location
     * @return SearchEvent
     */
    public function setLocation(?Location $location): SearchEvent
    {
        $this->location = $location;
        return $this;
    }
}
