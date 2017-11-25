<?php

namespace AppBundle\Search;

use AppBundle\Entity\City;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Description of SearchAgenda.
 *
 * @author Guillaume S. <guillaume@sainthillier.fr>
 */
class SearchAgenda
{
    /**
     * @var \DateTime
     * @Assert\Date()
     */
    protected $du;

    /**
     * @var \DateTime
     * @Assert\Date()
     */
    protected $au;

    /**
     * @var string
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
     * @var array
     */
    protected $commune;

    /**
     * @var string
     */
    protected $term;

    /**
     * @var int
     */
    protected $page;

    /**
     * @var City
     */
    protected $city;

    public function __construct()
    {
        $this->page = 1;
        $this->du   = new \DateTime();
    }

    public function getTerms()
    {
        return \array_unique(\array_filter(\explode(' ', $this->getTerm())));
    }

    /**
     * @param string $tag
     *
     * @return $this
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @return \DateTime
     */
    public function getDu()
    {
        return $this->du;
    }

    /**
     * @return \DateTime
     */
    public function getAu()
    {
        return $this->au;
    }

    /**
     * @return array
     */
    public function getTypeManifestation()
    {
        return $this->type_manifestation;
    }

    /**
     * @return array
     */
    public function getLieux()
    {
        return $this->lieux;
    }

    /**
     * @return string
     */
    public function getTerm()
    {
        return $this->term;
    }

    /**
     * @param City $city
     *
     * @return $this
     */
    public function setCity(City $city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @return City
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param \DateTime|null $du
     *
     * @return $this
     */
    public function setDu(\DateTime $du = null)
    {
        $this->du = $du;

        return $this;
    }

    /**
     * @param \DateTime|null $au
     *
     * @return $this
     */
    public function setAu(\DateTime $au = null)
    {
        $this->au = $au;

        return $this;
    }

    /**
     * @param $type_manifestation
     *
     * @return $this
     */
    public function setTypeManifestation($type_manifestation)
    {
        $this->type_manifestation = $type_manifestation;

        return $this;
    }

    /**
     * @param $lieux
     *
     * @return $this
     */
    public function setLieux($lieux)
    {
        $this->lieux = $lieux;

        return $this;
    }

    /**
     * @param $term
     *
     * @return $this
     */
    public function setTerm($term)
    {
        $this->term = $term;

        return $this;
    }

    /**
     * @return array
     */
    public function getCommune()
    {
        return $this->commune;
    }

    /**
     * @param $commune
     *
     * @return $this
     */
    public function setCommune($commune)
    {
        $this->commune = $commune;

        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param $page
     *
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }
}
