<?php

namespace App\Captcha;

use Symfony\Component\HttpFoundation\RequestStack;
use ReCaptcha\ReCaptcha;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 22:28.
 */
class CaptchaWrapper
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $secret;

    public function __construct(RequestStack $requestStack, $secret)
    {
        $this->requestStack = $requestStack;
        $this->secret       = $secret;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
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
