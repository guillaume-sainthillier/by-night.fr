<?php

namespace AppBundle\Parser;

/**
 * Description of ParserInterface.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
interface ParserInterface
{
    /**
     * @return array un tableau d'Agenda
     */
    public function parse();

    /*
     * @return string le nom de la classe
     */
    public function getNomData();
}
