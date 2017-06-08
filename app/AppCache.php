<?php

require_once __DIR__.'/AppKernel.php';

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;

class AppCache extends HttpCache
{
    protected function getOptions()
    {
        return [
            'debug'                  => true,
            'default_ttl'            => 0,
            'private_headers'        => ['Authorization', 'Cookie'],
            'allow_reload'           => true,
            'allow_revalidate'       => false,
            'stale_while_revalidate' => 2,
            'stale_if_error'         => 60,
        ];
    }
}
