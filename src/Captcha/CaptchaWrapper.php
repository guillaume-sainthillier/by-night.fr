<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Captcha;

use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaWrapper
{
    public function __construct(private RequestStack $requestStack, private ReCaptcha $reCaptcha)
    {
    }

    public function verify(?string $value): bool
    {
        $request = $this->requestStack->getMainRequest();
        $response = $this->reCaptcha->verify(
            $value,
            $request->getClientIp()
        );

        return $response->isSuccess();
    }
}
