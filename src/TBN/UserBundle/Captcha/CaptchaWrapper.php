<?php

namespace TBN\UserBundle\Captcha;

use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 22:28.
 */
class CaptchaWrapper
{
    private $requestStack;
    private $secret;

    public function __construct(RequestStack $requestStack, $secret)
    {
        $this->requestStack = $requestStack;
        $this->secret       = $secret;
    }

    public function verify($value)
    {
        $request = $this->requestStack->getMasterRequest();

        $reCaptcha = new ReCaptcha($this->secret);
        $response  = $reCaptcha->verify(
            $value,
            $request->getClientIp()
        );

        return $response->isSuccess();
    }
}
