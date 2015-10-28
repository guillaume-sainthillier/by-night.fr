<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TBN\MainBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use TBN\MainBundle\Site\SiteManager;

/**
 * Description of TBNExtension
 *
 * @author guillaume
 */
class MainExtension extends \Twig_Extension{

    private $requestStack;
    private $doctrine;
    private $cache;
    private $siteManager;

    public function __construct(RequestStack $requestStack, SiteManager $siteManager, $cache, $doctrine)
    {
	$this->requestStack = $requestStack;
	$this->cache        = $cache;
	$this->doctrine     = $doctrine;
        $this->siteManager  = $siteManager;
    }

    public function getGlobals()
    {
	$key = "sites";
	if(! $this->cache->contains($key))
	{
	    $repo = $this->doctrine->getRepository("TBNMainBundle:Site");
	    $this->cache->save($key, $repo->findBy([], ["nom" => "ASC"]), 0, 3);
	}
        
        return [
            "site"      => $this->siteManager->getCurrentSite(),
            "sites"     => $this->cache->fetch($key),
            "siteInfo"  => $this->siteManager->getSiteInfo()
        ];
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('resume', [$this, 'resume']),
            new \Twig_SimpleFilter('partial_extends', [$this, 'partialExtendsFilter']),
            new \Twig_SimpleFilter('url_decode', [$this, 'urlDecode'])
        ];
    }

    public function urlDecode($value)
    {
        return urldecode($value);
    }

    public function resume($texte)
    {
        $replaced_text = str_replace("&#13;",'<br>', $texte);
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
	if($request === null)
	{
	    return $template;
	}

	$isPJAX = ($request->headers->has("X-PJAX") || $request->isXmlHttpRequest());

	if(! $isPJAX)
	{
	    $suffix = "";
	}

	return preg_replace("/\.html(\.twig)?/i", $suffix.".html.twig", $template);
    }

    protected function trimBr($string){
        $string = preg_replace('/^\s*(?:<br\s*\/?>\s*)*/i', '', $string);
        $string = preg_replace('/\s*(?:<br\s*\/?>\s*)*$/i', '', $string);
        return	$string;
    }

    public function getName() {
        return "main_extension";
    }
}
