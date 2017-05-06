<?php

namespace SocialBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SocialBundle extends Bundle
{
    public function getParent()
    {
        return 'HWIOAuthBundle';
    }
}
