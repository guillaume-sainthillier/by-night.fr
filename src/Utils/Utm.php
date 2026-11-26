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
 * The UTM parameters that tell the analytics where a visit comes from when the browser says nothing of it: a mail app,
 * a calendar app or an AI assistant sends no referrer, and a widget's frame gives our own address. Without them such a
 * click counts as a direct visit. Umami keeps the query string apart from the path, so the visits of an event page
 * still add up (App\Analytics\EventVisitSync), and the canonical URL of a page never carries them.
 *
 * The e-mails worth measuring (the weekend selection, the outing alerts, the publishers' news) get theirs from
 * MailerManager as their "utm" context, which the |utm filter adds to their links; the security and transactional
 * e-mails have none, so their links stay as they are. The links given outside the site (MCP tools and API, widgets,
 * calendars) are tagged where they are built.
 */
final class Utm
{
    /**
     * @param string $source   the kind of e-mail: "weekend-digest", "outing-alert"
     * @param string $campaign what tells its sendings apart: the week, the edition
     *
     * @return array{utm_source: string, utm_medium: string, utm_campaign: string}
     */
    public static function email(string $source, string $campaign): array
    {
        return self::link($source, 'email', $campaign);
    }

    /**
     * @param string $source   where the link was given: "mcp", "api", "widget", "calendar"
     * @param string $medium   how: "assistant", "api", "embed", "calendar", "email"
     * @param string $campaign what tells its uses apart: the tool, the publisher or venue of a widget or a calendar
     *
     * @return array{utm_source: string, utm_medium: string, utm_campaign: string}
     */
    public static function link(string $source, string $medium, string $campaign): array
    {
        return ['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign];
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
