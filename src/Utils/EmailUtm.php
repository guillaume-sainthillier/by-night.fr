<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

/**
 * The UTM parameters that tell the analytics which e-mail a visit comes from: mail apps send no referrer, so without
 * them a click from an e-mail counts as a direct visit. MailerManager gives them to the e-mails worth measuring (the
 * weekend selection, the outing alerts, the publishers' news) as their "utm" context, which the |utm filter adds to
 * their links; the security and transactional e-mails have none, so their links stay as they are.
 */
final class EmailUtm
{
    /**
     * @param string $source   the kind of e-mail: "weekend-digest", "outing-alert"
     * @param string $campaign what tells its sendings apart: the week, the edition
     *
     * @return array{utm_source: string, utm_medium: string, utm_campaign: string}
     */
    public static function campaign(string $source, string $campaign): array
    {
        return ['utm_source' => $source, 'utm_medium' => 'email', 'utm_campaign' => $campaign];
    }

    /**
     * Adds the parameters to the query of a URL, before its fragment. A signed URL (UriSigner) must not get any: its
     * signature covers the query, an unsubscribe link would stop working.
     *
     * @param array<string, string> $utm
     */
    public static function tag(string $url, array $utm): string
    {
        if ([] === $utm) {
            return $url;
        }

        [$url, $fragment] = explode('#', $url, 2) + [1 => null];

        return $url
            . (str_contains($url, '?') ? '&' : '?')
            . http_build_query($utm, '', '&', \PHP_QUERY_RFC3986)
            . (null === $fragment ? '' : '#' . $fragment);
    }
}
