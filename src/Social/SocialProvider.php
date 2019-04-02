<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 04/12/2017
 * Time: 22:39.
 */

namespace App\Social;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialProvider
{
    const FACEBOOK = 'facebook';

    const FACEBOOK_LIST_EVENTS = 'facebook_list_events';

    const FACEBOOK_ADMIN = 'facebook_admin';

    const TWITTER = 'twitter';

    /**
     * @var array
     */
    private $socials;

    public function __construct(Facebook $facebook, FacebookListEvents $facebookListEvents, FacebookAdmin $facebookAdmin, Twitter $twitter)
    {
        $this->socials = [
            self::FACEBOOK             => $facebook,
            self::FACEBOOK_LIST_EVENTS => $facebookListEvents,
            self::FACEBOOK_ADMIN       => $facebookAdmin,
            self::TWITTER              => $twitter
        ];
    }

    /**
     * @param $name
     * @param string $default_facebook_name
     *
     * @return Social
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
