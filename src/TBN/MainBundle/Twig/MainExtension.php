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
use TBN\SocialBundle\Social\Facebook;
use TBN\SocialBundle\Social\FacebookAdmin;

/**
 * Description of TBNExtension
 *
 * @author guillaume
 */
class MainExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{

    public static $LIFE_TIME_CACHE = 86400; // 3600*24
    private $router;
    /**
     * @var RequestStack
     */
    private $requestStack;
    private $doctrine;
    private $cache;
    private $siteManager;
    private $socials;

    public function __construct(SiteManager $manager, ContainerInterface $container)
    {
        $this->router = $container->get('router');
        $this->requestStack = $container->get('request_stack');
        $this->cache = $container->get('memory_cache');
        $this->doctrine = $container->get('doctrine');
        $this->siteManager = $manager;
        $this->requestStack = $container->get('request_stack');
        $this->socials = [
            'facebook' => $container->get('tbn.social.facebook_admin'),
            'twitter' => $container->get('tbn.social.twitter'),
            'google' => $container->get('tbn.social.google')
        ];
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
            new \Twig_SimpleFilter('parse_tags', [$this, 'parseTags']),
            new \Twig_SimpleFilter('resume', [$this, 'resume']),
            new \Twig_SimpleFilter('partial_extends', [$this, 'partialExtendsFilter']),
            new \Twig_SimpleFilter('url_decode', [$this, 'urlDecode'])
        ];
    }

    public function getGlobals()
    {
        $globals = [];
        $site = $this->siteManager->getCurrentSite();
        if ($site !== null && $this->requestStack->getParentRequest() === null) {
            $key = "sites." . $site->getSubdomain();
            if (!$this->cache->contains($key)) {
                $repo = $this->doctrine->getRepository("TBNMainBundle:Site");
                $sites = $repo->findRandom($site);
                $nomSites = [];
                foreach ($sites as $site) {
                    $nomSites[] = ['nom' => $site->getNom(), 'subdomain' => $site->getSubdomain()];
                }
                $this->cache->save($key, $nomSites);
            }
            $sites = $this->cache->fetch($key);

            $globals = [
                "site" => $this->siteManager->getCurrentSite(),
                "sites" => $sites,
                "siteInfo" => $this->siteManager->getSiteInfo()
            ];

            foreach ($this->socials as $name => $social) {
                $key = 'tbn.counts.' . $name;
                if (!$this->cache->contains($key)) {
                    if($social instanceof FacebookAdmin) {
                        $social->init();
                    }
                    $this->cache->save($key, $social->getNumberOfCount(), self::$LIFE_TIME_CACHE);
                }

                $globals['count_' . $name] = $this->cache->fetch($key);
            }
        }
        return $globals;
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
        $diff = $date->diff(new \DateTime);


        if ($diff->y > 0) //Années
        {
            $message = sprintf("Il y a %d %s", $diff->y, "an" . ($diff->y > 1 ? "s" : ""));
        } else if ($diff->m > 0) //Mois
        {
            $message = sprintf("Il y a %d mois", $diff->m);
        } else if ($diff->d > 0) //Jours
        {
            $message = sprintf("Il y a %d jours", $diff->d);
        } else if ($diff->h > 0) //Heures
        {
            $message = sprintf("Il y a %d %s", $diff->h, "heure" . ($diff->h > 1 ? "s" : ""));
        } else if ($diff->i > 0) //Minutes
        {
            $message = sprintf("Il y a %d %s", $diff->i, "minute" . ($diff->i > 1 ? "s" : ""));
        } else if ($diff->s > 30) //Secondes
        {
            $message = sprintf("Il y a %d secondes", $diff->s);
        } else {
            $message = "A l'instant";
        }

        return $message;
    }

    public function getAuthorizationSiteUrl($name)
    {
        return $this->router->generate("tbn_administration_connect_site", ["service" => $name]);
    }

    public function getLogoutSiteUrl($name)
    {
        return $this->router->generate("tbn_administration_disconnect_site", ["service" => $name]);
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
