<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialProvider
{
    public const FACEBOOK = 'facebook';
    public const FACEBOOK_ADMIN = 'facebook_admin';
    public const TWITTER = 'twitter';
    public const TWITTER_ADMIN = 'twitter_admin';
    public const GOOGLE = 'google';
    public const GOOGLE_ADMIN = 'google_admin';

    private array $socials;

    public function __construct(Facebook $facebook, FacebookAdmin $facebookAdmin, Twitter $twitter, Google $google)
    {
        $this->socials = [
            self::FACEBOOK => $facebook,
            self::FACEBOOK_ADMIN => $facebookAdmin,
            self::TWITTER => $twitter,
            self::TWITTER_ADMIN => $twitter,
            self::GOOGLE => $google,
            self::GOOGLE_ADMIN => $google,
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
            throw new NotFoundHttpException(sprintf('Unable to find social service with id "%s"', $name));
        }

        return $this->socials[$name];
    }
}
