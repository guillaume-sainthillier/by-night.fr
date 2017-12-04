<?php

namespace App\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Parser\Common\SoonNightParser;

class SoonNightParserTest extends KernelTestCase
{
    /**
     * @var SoonNightParser
     */
    protected $parser;

    public function setUp()
    {
        self::bootKernel();

        $this->parser = static::$kernel->getContainer()->get(SoonNightParser::class);
    }

    public function testNormalize()
    {
        list($rue, $codePostal, $ville) = $this->parser->normalizeAddress('46 rue des lombards, 75001 Paris');
        $this->assertEquals($rue, '46 rue des lombards');
        $this->assertEquals($codePostal, '75001');
        $this->assertEquals($ville, 'Paris');

        list($rue, $codePostal, $ville) = $this->parser->normalizeAddress('46, rue des lombards, 75001 Paris');
        $this->assertEquals($rue, '46 rue des lombards');
        $this->assertEquals($codePostal, '75001');
        $this->assertEquals($ville, 'Paris');

        list($rue, $codePostal, $ville) = $this->parser->normalizeAddress('Canal de l\'Ourcq - Parc de la Villette, 59 Boulevard Macdonald, 75019 Paris');
        $this->assertEquals($rue, '59 Boulevard Macdonald');
        $this->assertEquals($codePostal, '75019');
        $this->assertEquals($ville, 'Paris');

        list($rue, $codePostal, $ville) = $this->parser->normalizeAddress('Escale de Passy, parking Passy face à la maison de la radio, 75016 Paris');
        $this->assertEquals($rue, 'parking Passy face à la maison de la radio');
        $this->assertEquals($codePostal, '75016');
        $this->assertEquals($ville, 'Paris');

        list($rue, $codePostal, $ville) = $this->parser->normalizeAddress('Zi Haie Passart - 1 rue Industrie , 77170 Brie compte robert');
        $this->assertEquals($rue, '1 rue Industrie');
        $this->assertEquals($codePostal, '77170');
        $this->assertEquals($ville, 'Brie compte robert');

        list($rue, $codePostal, $ville) = $this->parser->normalizeAddress('Pont Fol , 56400 Ploemel, Morbihan');
        $this->assertEquals($rue, 'Pont Fol');
        $this->assertEquals($codePostal, '56400');
        $this->assertEquals($ville, 'Ploemel');
    }
}
