<?php

namespace AppBundle\Parser;

use AppBundle\Entity\Agenda;

/**
 * Description of ParserInterface.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
interface ParserInterface
{
    /**
     * @return Agenda[] un tableau d'Agenda parsé
     */
    public function parse();

    /*
     * @return string le nom de la classe
     */
    public function getNomData();
}
