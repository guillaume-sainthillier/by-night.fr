<?php

namespace App\Social;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialProvider
{
    const FACEBOOK = 'facebook';
    const FACEBOOK_ADMIN = 'facebook_admin';
    const TWITTER = 'twitter';
    const GOOGLE = 'google';
    const EVENTBRITE = 'eventbrite';

    /**
     * @var array
     */
    private $socials;

    public function __construct(Facebook $facebook, FacebookAdmin $facebookAdmin, Twitter $twitter, Google $google, EventBrite $eventBrite)
    {
        $this->socials = [
            self::FACEBOOK => $facebook,
            self::FACEBOOK_ADMIN => $facebookAdmin,
            self::TWITTER => $twitter,
            self::GOOGLE => $google,
            self::EVENTBRITE => $eventBrite,
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
