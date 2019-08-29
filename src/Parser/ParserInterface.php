<?php

namespace App\Parser;

/**
 * Description of ParserInterface.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
interface ParserInterface
{
    /**
     * @return int le nombre d'items parsés
     */
    public function parse(): int;

    public function publish(array $item): void;

    /**
     * @return string le nom de la classe
     */
    public function getNomData(): string;
}
