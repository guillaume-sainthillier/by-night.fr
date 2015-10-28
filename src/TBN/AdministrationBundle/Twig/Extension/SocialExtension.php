<?php

/*
 * This file is part of the HWIOAuthBundle package.
 *
 * (c) Hardware.Info <opensource@hardware.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TBN\AdministrationBundle\Twig\Extension;

use BeSimple\I18nRoutingBundle\Routing\Router;

/**
 * OAuthExtension
 *
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
class SocialExtension extends \Twig_Extension
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * @param OAuthHelper $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('tbn_oauth_authorization_site_url', [$this, 'getAuthorizationSiteUrl']),
            new \Twig_SimpleFunction('tbn_oauth_logout_site_url', [$this, 'getLogoutSiteUrl'])
        ];
    }


    /**
     * @param string $name
     *
     * @return string
     */
    public function getAuthorizationSiteUrl($name)
    {
        return $this->router->generate("tbn_administration_connect_site", ["service" => $name]);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getLogoutSiteUrl($name)
    {
        return $this->router->generate("tbn_administration_disconnect_site", ["service" => $name]);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'tbn_oauth';
    }
}
