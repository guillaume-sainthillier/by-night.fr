<?php

namespace App\Exception;

use Exception;

class SocialException extends Exception
{
    /**
     * @var string
     */
    protected $type;

    public function __construct($message, $type = 'warning', $code = 500, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
