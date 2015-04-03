<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new BeSimple\I18nRoutingBundle\BeSimpleI18nRoutingBundle(),
	    new Debril\RssAtomBundle\DebrilRssAtomBundle(),
            new TBN\MainBundle\TBNMainBundle(),
            new FOS\UserBundle\FOSUserBundle(),
            new HWI\Bundle\OAuthBundle\HWIOAuthBundle(),
            new Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle(),
            new TBN\AgendaBundle\TBNAgendaBundle(),
            new TBN\MajDataBundle\TBNMajDataBundle(),
            new TBN\UserBundle\TBNUserBundle(),
            new TBN\SocialBundle\TBNSocialBundle(),
            new TBN\CommentBundle\TBNCommentBundle(),
            new TBN\AdministrationBundle\TBNAdministrationBundle(),

            new WhiteOctober\BreadcrumbsBundle\WhiteOctoberBreadcrumbsBundle(),
            new Genemu\Bundle\FormBundle\GenemuFormBundle(),
            new Liip\ImagineBundle\LiipImagineBundle(),
            new \Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle(),
	    new Ivory\GoogleMapBundle\IvoryGoogleMapBundle(),
            new \JMS\SerializerBundle\JMSSerializerBundle(),
            new WhiteOctober\PagerfantaBundle\WhiteOctoberPagerfantaBundle(),
            new \FOS\ElasticaBundle\FOSElasticaBundle(),
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
}
