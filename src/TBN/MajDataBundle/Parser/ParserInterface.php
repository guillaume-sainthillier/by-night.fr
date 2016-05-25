<?php

namespace TBN\MajDataBundle\Parser;

/**
 * Description of ParserInterface
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
interface ParserInterface
{

    /**
     * @return Agenda[] un tableau d'Agenda parsé
     */
    public function parse();

    /**
     * @return boolean vrai si la place n'a pas besoin d'être vérifiée géographiquement, false sinon
     */
    public function isTrustedLocation();

    /*
     * @return string le nom de la classe
     */
    public function getNomData();
}
