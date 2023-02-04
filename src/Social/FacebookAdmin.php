<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

class FacebookAdmin extends Facebook
{
    /**
     * @var string
     */
    private const BASE_GRAPH_URL = 'https://graph.facebook.com';

    /**
     * @return string[]
     *
     * @psalm-return array<string>
     */
    public function getUserImagesFromIds(array $ids_users): array
    {
        $urls = [];
        foreach ($ids_users as $id_user) {
            $urls[$id_user] = sprintf(
                '%s/%s/picture?width=1500&height=1500',
                self::BASE_GRAPH_URL,
                $id_user
            );
        }

        return $urls;
    }
}
