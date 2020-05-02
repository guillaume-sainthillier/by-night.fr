<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\OAuth;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class TwitterUser implements ResourceOwnerInterface
{
    private array $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getId()
    {
        return $this->response['id'];
    }

    public function getEmail(): ?string
    {
        return $this->getResponseValue('email');
    }

    public function getName(): ?string
    {
        return $this->getResponseValue('name');
    }

    public function getScreenName(): ?string
    {
        return $this->getResponseValue('screen_name');
    }

    public function getProfilePicture(): ?string
    {
        return $this->getResponseValue('profile_background_image_url_https') ?: $this->getResponseValue('profile_background_image_url');
    }

    /**
     * Get user data as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }

    private function getResponseValue($key)
    {
        if (\array_key_exists($key, $this->response)) {
            return $this->response[$key];
        }

        return null;
    }
}
