<?php

namespace App\Social;

use BadMethodCallException;

class Google extends Social
{
    public function constructClient()
    {
    }

    public function getNumberOfCount()
    {
        throw new BadMethodCallException('Not implemented');
    }

    protected function getName()
    {
        return 'Google';
    }
}
