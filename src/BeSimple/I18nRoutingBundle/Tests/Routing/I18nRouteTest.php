<?php

namespace BeSimple\I18nRoutingBundle\Tests\Routing;

use BeSimple\I18nRoutingBundle\Routing\I18nRoute;

/**
 * @author Francis Besset <francis.besset@gmail.com>
 */
class I18nRouteTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideI18nRouteData
     */
    public function testCollection($name, $locales, $defaults, $requirements, $options, $variables)
    {
        $i18nRoute  = new I18nRoute($name, $locales, $defaults, $requirements, $options);
        $collection = $i18nRoute->getCollection();

        foreach ($locales as $locale => $pattern) {
            $route = $collection->get($name.'.'.$locale);
            $compiled = $route->compile();

            $defaults['_locale']       = $locale;
            $options['compiler_class'] = 'Symfony\\Component\\Routing\\RouteCompiler';

            $this->assertEquals($pattern, $route->getPattern(), '(pattern)');
            $this->assertEquals($defaults, $route->getDefaults(), '(defaults)');
            $this->assertEquals($requirements, $route->getRequirements(), '(requirements)');
            $this->assertEquals($options, $route->getOptions(), '(options)');
            $this->assertEquals($variables, $compiled->getVariables(), '(variables)');
        }
    }

    public function provideI18nRouteData()
    {
        return [
            [
                'static_route',
                ['en' => '/welcome', 'fr' => '/bienvenue', 'de' => '/willkommen'],
                [],
                [],
                [],
                [],
            ],

           [
                'dynamic_route',
                ['en' => '/en/{page}', 'fr' => '/fr/{page}', 'de' => '/de/{page}'],
                [],
                [],
                [],
                ['page'],
            ],

            [
                'default_route',
                ['en' => '/en/{page}', 'fr' => '/fr/{page}', 'de' => '/de/{page}'],
                ['page' => 'index.html'],
                [],
                [],
                ['page'],
            ],

            [
                'requirement_route',
                ['en' => '/en/{page}.{extension}', 'fr' => '/fr/{page}.{extension}', 'de' => '/de/{page}.{extension}'],
                ['page' => 'index.html'],
                ['extension' => 'html|xml|json'],
                [],
                ['page', 'extension'],
            ],

            [
                'option_route',
                ['en' => '/en/{page}.{extension}', 'fr' => '/fr/{page}.{extension}', 'de' => '/de/{page}.{extension}'],
                ['page' => 'index.html'],
                ['page' => '\d+', 'extension' => 'html|xml|json'],
                ['page' => 1],
                ['page', 'extension'],
            ],

            [
                'other_locales_route',
                ['en_GB' => '/en/{page}.{extension}', 'fr_FR' => '/fr/{page}.{extension}', 'de_DE' => '/de/{page}.{extension}'],
                ['page' => 'index.html'],
                ['page' => '\d+', 'extension' => 'html|xml|json'],
                ['page' => 1],
                ['page', 'extension'],
            ],
        ];
    }
}
