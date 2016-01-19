<?php

namespace TBN\AgendaBundle\Search;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Description of SearchAgenda
 *
 * @author Guillaume S. <guillaume@sainthillier.fr>
 */
class SearchAgenda {
    
    /**
    *
    * @var \DateTime
    * @Assert\Date()
    */
    protected $du;
    
    /**
     *
     * @var \DateTime
     * @Assert\Date()
     */
    protected $au;
    
    /**
     *
     * @var array
     */
    protected $type_manifestation;
    
    /**
     *
     * @var array
     */
    protected $lieux;

    /**
     *
     * @var array
     */
    protected $commune;
    
    /**
     *
     * @var string
     */
    protected $term;

    /**
     *
     * @var integer
     */
    protected $page;
    
    
    public function __construct()
    {
	$this->page = 1;
        $this->du = new \DateTime;
    }

    public function getTerms()
    {
        return array_unique(array_filter(explode(" ", $this->getTerm())));
    }
    
    public function getDu() {
        return $this->du;
    }

    public function getAu() {
        return $this->au;
    }

    public function getTypeManifestation() {
        return $this->type_manifestation;
    }

    public function getLieux() {
        return $this->lieux;
    }

    public function getTerm() {
        return $this->term;
    }

    public function setDu(\DateTime $du = null) {
        $this->du = $du;
        return $this;
    }

    public function setAu(\DateTime $au = null) {
        $this->au = $au;
        return $this;
    }

    public function setTypeManifestation($type_manifestation) {
        $this->type_manifestation = $type_manifestation;
        return $this;
    }

    public function setLieux($lieux) {
        $this->lieux = $lieux;
        return $this;
    }

    public function setTerm($term) {
        $this->term = $term;
        return $this;
    }

    public function getCommune() {
        return $this->commune;
    }

    public function setCommune($commune) {
        $this->commune = $commune;
        return $this;
    }

    public function getPage() {
	return $this->page;
    }

    public function setPage($page) {
	$this->page = $page;
	return $this;
    }
}
