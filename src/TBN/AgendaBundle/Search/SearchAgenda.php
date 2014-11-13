<?php

namespace TBN\AgendaBundle\Search;

/**
 * Description of SearchAgenda
 *
 * @author Guillaume S. <guillaume@sainthillier.fr>
 */
class SearchAgenda {
    
    /**
     *
     * @var \DateTime
     */
    protected $du;
    
    /**
     *
     * @var \DateTime
     */
    protected $au;
    
    /**
     *
     * @var array
     */
    protected $type_manifestation;
    
    /**
     *
     * @var string
     */
    protected $commune;
    
    /**
     *
     * @var string
     */
    protected $theme;
    
    /**
     *
     * @var string
     */
    protected $term;
    
    
    public function __construct()
    {
        $this->du = new \DateTime;
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

    public function getCommune() {
        return $this->commune;
    }

    public function getTheme() {
        return $this->theme;
    }

    public function getTerm() {
        return $this->term;
    }

    public function setDu($du) {
        $this->du = $du;

        return $this;
    }

    public function setAu($au) {
        $this->au = $au;
    }

    public function setTypeManifestation($type_manifestation) {
        $this->type_manifestation = $type_manifestation;
    }

    public function setCommune($commune) {
        $this->commune = $commune;
    }

    public function setTheme($theme) {
        $this->theme = $theme;
    }

    public function setTerm($term) {
        $this->term = $term;

        return $this;
    }


}
