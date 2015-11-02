<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TBN\CommentBundle\Twig;

/**
 * Description of TBNExtension
 *
 * @author guillaume
 */
class TBNExtension extends \Twig_Extension{

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('diff_date', [$this, 'diffDate']),
            new \Twig_SimpleFilter('parse_tags', [$this, 'parseTags']),
        ];
    }

    

    public function getName() {
        return "tbn_extension";
    }
}
