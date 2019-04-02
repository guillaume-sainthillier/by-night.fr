<?php

namespace App\Social;

use BadMethodCallException;

/**
 * Description of Twitter.
 *
 * @author guillaume
 */
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
