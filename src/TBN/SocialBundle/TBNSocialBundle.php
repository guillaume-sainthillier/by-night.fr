<?php

namespace TBN\SocialBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TBNSocialBundle extends Bundle
{
    public function getParent()
    {
        return 'HWIOAuthBundle';
    }
}
