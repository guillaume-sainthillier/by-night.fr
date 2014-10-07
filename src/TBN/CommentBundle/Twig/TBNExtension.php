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
            'diff_date' => new \Twig_Filter_Method($this, 'diffDate'),
            'parse_tags' => new \Twig_Filter_Method($this, 'parseTags'),
        ];
    }

    public function parseTags($texte)
    {
        $regex = "((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)";
        $regex_2 = "#href=['\"]([^'^\"]+)['\"]#i";

        if(preg_match($regex_2, $texte))
        {
            $texte = preg_replace(
                $regex_2,
                "href=\"$1\" rel=\"nofollow\"",
                $texte
            );
        }else
        {
            $texte = preg_replace(
                "#".$regex."#ie",
                "'<a rel=\"nofollow\" href=\"$1\" target=\"_blank\">$3</a>$4'",
                $texte
            );
        }

        return $texte;
    }

    public function diffDate(\DateTime $date)
    {
        $diff = $date->diff(new \DateTime);


        if($diff->y > 0) //Années
        {
            $message = sprintf("Il y a %d %s",$diff->y, "an".($diff->y > 1 ? "s" : ""));
        }else if($diff->m > 0) //Mois
        {
            $message = sprintf("Il y a %d mois",$diff->m);
        }else if($diff->d > 0) //Jours
        {
            $message = sprintf("Il y a %d jours",$diff->d);
        }else if($diff->h > 0) //Heures
        {
            $message = sprintf("Il y a %d %s",$diff->h, "heure".($diff->h > 1 ? "s" : ""));
        }else if($diff->i > 0) //Minutes
        {
            $message = sprintf("Il y a %d %s",$diff->i, "minute".($diff->i > 1 ? "s" : ""));
        }else if($diff->s > 30) //Secondes
        {
            $message = sprintf("Il y a %d secondes",$diff->s);
        }else{
            $message = "A l'instant";
        }

        return $message;
    }

    public function priceFilter($number, $decimals = 0, $decPoint = '.', $thousandsSep = ',')
    {
        $price = number_format($number, $decimals, $decPoint, $thousandsSep);
        $price = '$' . $price;

        return $price;
    }

    public function getName() {
        return "tbn_extension";
    }
}
