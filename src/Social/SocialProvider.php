<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialProvider
{
    const FACEBOOK = 'facebook';
    const FACEBOOK_ADMIN = 'facebook_admin';
    const TWITTER = 'twitter';
    const GOOGLE = 'google';

    /**
     * @var array
     */
    private $socials;

    public function __construct(Facebook $facebook, FacebookAdmin $facebookAdmin, Twitter $twitter, Google $google)
    {
        $this->socials = [
            self::FACEBOOK => $facebook,
            self::FACEBOOK_ADMIN => $facebookAdmin,
            self::TWITTER => $twitter,
            self::GOOGLE => $google,
        ];
    }

    /**
     * @param $name
     * @param string $default_facebook_name
     */
    public function getSocial($name, $default_facebook_name = self::FACEBOOK): Social
    {
        if (self::FACEBOOK === $name) {
            $name = $default_facebook_name;
        }

        if (!isset($this->socials[$name])) {
            throw new NotFoundHttpException(\sprintf('Unable to find social service with id "%s"', $name));
        }

        return $this->socials[$name];
    }
}
