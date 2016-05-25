<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TBN\SocialBundle\Exception;
/**
 * Description of SocialException
 *
 * @author guillaume
 */
class SocialException extends \Exception
{

    protected $type;

    public function __construct($message, $type = "warning", $code = 500, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }
}
