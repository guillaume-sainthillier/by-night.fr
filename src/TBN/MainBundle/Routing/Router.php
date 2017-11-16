<?php

namespace TBN\MainBundle\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\Router as BaseRouter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\RequestContext;

class Router extends BaseRouter
{
    private $cache;
    private $siteManager;
    private $subdomain;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param mixed              $resource  The main resource to load
     * @param array              $options   An array of options
     * @param RequestContext     $context   The context
     */
    public function __construct(ContainerInterface $container, $resource, array $options = [], RequestContext $context = null)
    {
        parent::__construct($container, $resource, $options, $context);

        $this->subdomain   = null;
        $this->siteManager = $container->get('site_manager');
        $this->cache       = $container->get('array_cache');
    }

    /**
     * {@inheritdoc}
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH)
    {
        if (!$this->subdomain && $this->siteManager->getCurrentSite()) {
            $this->subdomain = $this->siteManager->getCurrentSite()->getSubdomain();
        }

        $key = 'routes.'.$name;

        try {
            if ($this->cache->contains($key) && !isset($parameters) && $this->subdomain) {
                $parameters['subdomain'] = $this->subdomain;
            }

            return parent::generate($name, $parameters, $referenceType);
        } catch (MissingMandatoryParametersException $e) {
            $this->cache->save($key, true);
            if ($this->subdomain) {
                $parameters['subdomain'] = $this->subdomain;

                return parent::generate($name, $parameters, $referenceType);
            }

            throw $e;
        }
    }
}
