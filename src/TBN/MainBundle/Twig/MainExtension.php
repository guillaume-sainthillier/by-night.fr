<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TBN\MainBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use TBN\MainBundle\Site\SiteManager;


/**
 * Description of TBNExtension
 *
 * @author guillaume
 */
class MainExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    private $router;
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SiteManager
     */
    private $siteManager;

    public function __construct(SiteManager $manager, ContainerInterface $container)
    {
        $this->router = $container->get('router');
        $this->requestStack = $container->get('request_stack');
        $this->siteManager = $manager;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('tbn_oauth_authorization_site_url', [$this, 'getAuthorizationSiteUrl']),
            new \Twig_SimpleFunction('tbn_oauth_logout_site_url', [$this, 'getLogoutSiteUrl'])
        ];
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('diff_date', [$this, 'diffDate']),
            new \Twig_SimpleFilter('stats_diff_date', [$this, 'statsDiffDate']),
            new \Twig_SimpleFilter('parse_tags', [$this, 'parseTags']),
            new \Twig_SimpleFilter('resume', [$this, 'resume']),
            new \Twig_SimpleFilter('partial_extends', [$this, 'partialExtendsFilter']),
            new \Twig_SimpleFilter('url_decode', [$this, 'urlDecode']),
            new \Twig_SimpleFilter('tweet', [$this, 'tweet']),
            new \Twig_SimpleFilter('datetime', [$this, 'getDateTime'])
        ];
    }

    public function getGlobals()
    {
        $site = $this->siteManager->getCurrentSite();

        if(! $site) {
            return [
                "site" => null,
                "siteInfo" => null
            ];
        }
        
        return [
            "site" => $this->siteManager->getCurrentSite(),
            "siteInfo" => $this->siteManager->getSiteInfo()
        ];
    }

    public function getDateTime($string) {
        return new \DateTime($string);
    }

    public function tweet($tweet) {
        $linkified = '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@';
        $hashified = '/(^|[\n\s])#([^\s"\t\n\r<:]*)/is';
        $mentionified = '/(^|[\n\s])@([^\s"\t\n\r<:]*)/is';

        $prettyTweet = preg_replace(
            array(
                $linkified,
                $hashified,
                $mentionified
            ),
            array(
                '<a href="$1" class="link-tweet" target="_blank">$1</a>',
                '$1<a class="link-hashtag" href="https://twitter.com/search?q=%23$2&src=hash" target="_blank">#$2</a>',
                '$1<a class="link-mention" href="http://twitter.com/$2" target="_blank">@$2</a>'
            ),
            $tweet
        );

        return $prettyTweet;
    }

    public function parseTags($texte)
    {
        $texte = preg_replace("#<a(.*)href=['\"]([^'^\"]*)['\"]([^>]*)>#", "<a href=\"$2\" target=\"_blank\" rel=\"nofollow\">", $texte);
        $texte = preg_replace("#(^|[\n ])((http|https|ftp)://)?([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a href=\"\\4\" target=\"_blank\" rel=\"nofollow\">\\4</a>", $texte);

        if (!preg_match("/<(.*)(script|style|link)/i", $texte)) {
            return $texte;
        }

        return strip_tags($texte, "<a><abbr><acronym><address><article><aside><b><bdo><big><blockquote><br><caption><cite><code><col><colgroup><dd><del><details><dfn><div><dl><dt><em><figcaption><figure><font><h1><h2><h3><h4><h5><h6><hgroup><hr><i><img><ins><li><map><mark><menu><meter><ol><p><pre><q><rp><rt><ruby><s><samp><section><small><span><strong><style><sub><summary><sup><table><tbody><td><tfoot><th><thead><time><tr><tt><u><ul><var><wbr>");
    }

    public function diffDate(\DateTime $date)
    {
        return $this->statsDiffDate($date)['full'];
    }

    public function statsDiffDate(\DateTime $date)
    {
        $diff = $date->diff(new \DateTime);

        if ($diff->y > 0) //Années
        {
            return [
                'short' => sprintf("%d an%s", $diff->y, $diff->y > 1 ? "s" : ""),
                'long' => sprintf("%d an%s", $diff->y, $diff->y > 1 ? "s" : ""),
                'full' => sprintf("Il y a %d %s", $diff->y, $diff->y > 1 ? "s" : "")
            ];
        } else if ($diff->m > 0) //Mois
        {
            return [
                'short' => sprintf("%d mois", $diff->m),
                'long' => sprintf("%d mois", $diff->m),
                'full' => sprintf("Il y a %d mois", $diff->m)
            ];
        } else if ($diff->d > 0) //Jours
        {
            return [
                'short' => sprintf("%d j", $diff->d),
                'long' => sprintf("%d jours", $diff->d),
                'full' => sprintf("Il y a %d jours", $diff->d)
            ];
        } else if ($diff->h > 0) //Heures
        {
            return [
                'short' => sprintf("%d h", $diff->h),
                'long' => sprintf("%d heure%s", $diff->h, $diff->h > 1 ? "s" : ""),
                'full' => sprintf("Il y a %d heure%s", $diff->h, "heure" . $diff->h > 1 ? "s" : "")
            ];
        } else if ($diff->i > 0) //Minutes
        {
            return [
                'short' => sprintf("%d min", $diff->i),
                'long' => sprintf("%d minute%s", $diff->i, $diff->i > 1 ? "s" : ""),
                'full' => sprintf("Il y a %d minute%s", $diff->i, $diff->i > 1 ? "s" : "")
            ];
        } else if ($diff->s > 30) //Secondes
        {
            return [
                'short' => sprintf("%d s", $diff->s),
                'long' => sprintf("%d seconde%s", $diff->s, $diff->s > 1 ? "s" : ""),
                'full' => sprintf("Il y a %d seconde%s", $diff->s, $diff->s > 1 ? "s" : "")
            ];
        }

        return [
            'short' => sprintf("0 s"),
            'long' => sprintf("à l'instant"),
            'full' => sprintf("A l'instant")
        ];
    }

    public function getAuthorizationSiteUrl($name)
    {
        return $this->router->generate("tbn_administration_connect_site", ["service" => $name]);
    }

    public function getLogoutSiteUrl($name)
    {
        return $this->router->generate("tbn_administration_site_service", ["service" => $name]);
    }


    public function urlDecode($value)
    {
        return urldecode($value);
    }

    public function resume($texte)
    {
        $replaced_text = str_replace("&#13;", '<br>', $texte);
        $stripped_text = strip_tags($replaced_text);
        $shorted_text = substr($stripped_text, 0, 250);


        //striptags[:250]|replace({'&#13;': '<br>'})|trim|raw|trim('<br><br />')|raw
        $linked_text = preg_replace("
            #((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#ie",
            "'<a rel=\"nofollow\" href=\"$1\" target=\"_blank\">$3</a>$4'",
            $shorted_text
        );

        $final_text = $this->trimBr($linked_text);

        return trim($final_text);
    }

    public function partialExtendsFilter($template, $suffix = ".partial")
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $template;
        }

        $isPJAX = ($request->headers->has("X-PJAX") || $request->isXmlHttpRequest());

        if (!$isPJAX) {
            $suffix = "";
        }

        return preg_replace("/\.html(\.twig)?/i", $suffix . ".html.twig", $template);
    }

    protected function trimBr($string)
    {
        $string = preg_replace('/^\s*(?:<br\s*\/?>\s*)*/i', '', $string);
        $string = preg_replace('/\s*(?:<br\s*\/?>\s*)*$/i', '', $string);
        return $string;
    }

    public function getName()
    {
        return "main_extension";
    }
}
