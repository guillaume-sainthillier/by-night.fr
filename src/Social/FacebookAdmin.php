<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use App\Entity\AppOAuth;
use Facebook\FacebookClient;

class FacebookAdmin extends Facebook
{
    private AppOAuth $appOAuth;
    private bool $_isInitialized;

    protected function init()
    {
        parent::init();

        if (!$this->_isInitialized) {
            $this->_isInitialized = true;
            $this->appOAuth = $this->socialManager->getAppOAuth();

            if ($this->appOAuth && $this->appOAuth->getFacebookAccessToken()) {
                $this->client->setDefaultAccessToken($this->appOAuth->getFacebookAccessToken());
            }
        }
    }

    public function getPageFromId($id_page, $params = [])
    {
        $this->init();
        $accessToken = $this->appOAuth ? $this->appOAuth->getFacebookAccessToken() : null;
        $request = $this->client->sendRequest('GET',
            '/' . $id_page,
            $params,
            $accessToken
        );

        return $request->getGraphPage();
    }

    public function getUserImagesFromIds(array $ids_users)
    {
        $urls = [];
        foreach ($ids_users as $id_user) {
            $urls[$id_user] = sprintf(
                '%s/%s/picture?width=1500&height=1500',
                FacebookClient::BASE_GRAPH_URL,
                $id_user
            );
        }

        return $urls;
    }
}
