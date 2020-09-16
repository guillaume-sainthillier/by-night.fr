<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Captcha;

use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaWrapper
{
    private RequestStack $requestStack;

    private string $secret;

    public function __construct(RequestStack $requestStack, $secret)
    {
        $this->requestStack = $requestStack;
        $this->secret = $secret;
    }

    /**
     * @return bool
     */
    public function verify(?string $value): bool
    {
        $request = $this->requestStack->getMasterRequest();

        $reCaptcha = new ReCaptcha($this->secret);
        $response = $reCaptcha->verify(
            $value,
            $request->getClientIp()
        );

        return $response->isSuccess();
    }
}
