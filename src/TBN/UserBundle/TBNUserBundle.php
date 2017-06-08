<?php

namespace TBN\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TBNUserBundle extends Bundle
{
    public function getParent()
    {
        return 'FOSUserBundle';
    }
}
