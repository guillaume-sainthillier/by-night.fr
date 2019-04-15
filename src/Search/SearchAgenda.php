<?php

namespace App\Search;

use App\App\Location;
use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class SearchAgenda
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
     * @return SearchAgenda
     */
    public function setDu(?DateTime $du): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setAu(?DateTime $au): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setTag(?string $tag): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setTypeManifestation(array $type_manifestation): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setLieux(array $lieux): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setTerm(?string $term): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setPage(int $page): SearchAgenda
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
     * @return SearchAgenda
     */
    public function setLocation(?Location $location): SearchAgenda
    {
        $this->location = $location;
        return $this;
    }
}
