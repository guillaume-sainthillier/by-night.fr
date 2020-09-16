<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

class ReservationsHandler
{
    //https://mathiasbynens.be/demo/url-regex
    private string $urlRegex = '_^(?:(?:https?|ftp)://)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';

    //https://regex101.com/r/dsi0Pw/2
    private string $phoneRegex = '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/';

    //https://emailregex.com/
    private string $emailRegex = '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD';

    public function parseReservations(?string $text): array
    {
        if (null === $text) {
            return [
                'urls' => null,
                'phones' => null,
                'emails' => null,
            ];
        }

        $urls = [];
        $phones = [];
        $emails = [];

        while (true) {
            $originalText = $text;

            //First, we try with plain text
            [$urls, $phones, $emails, $text] = $this->getParts('', $text, $urls, $phones, $emails);

            //Then, we try to split with ','
            [$urls, $phones, $emails, $text] = $this->getParts(',', $text, $urls, $phones, $emails);

            //Then try to split with ' '
            [$urls, $phones, $emails, $text] = $this->getParts(' ', $text, $urls, $phones, $emails);

            //None of try has worked, we can skip
            if ($originalText === $text) {
                break;
            }
        }

        return [
            'urls' => array_unique($urls) ?: null,
            'phones' => array_unique($phones) ?: null,
            'emails' => array_unique($emails) ?: null,
        ];
    }

    private function getPart(string $delimiter, string $regex, string $text, array $accu): array
    {
        $text = str_replace($accu, '', $text);
        $parts = array_filter(array_map('trim', '' === $delimiter ? [$text] : explode($delimiter, $text)));
        foreach ($parts as $part) {
            if (preg_match_all($regex, $part, $matches)) {
                $accu = array_merge($accu, $this->getFirstMatches($matches));
            }
        }

        $text = str_replace($accu, '', $text);

        return [$text, $accu];
    }

    private function getParts(string $delimiter, string $text, array $urls = [], array $phones = [], array $emails = []): array
    {
        [$text, $urls] = $this->getPart($delimiter, $this->urlRegex, $text, $urls);
        [$text, $emails] = $this->getPart($delimiter, $this->emailRegex, $text, $emails);
        [$text, $phones] = $this->getPart($delimiter, $this->phoneRegex, $text, $phones);

        return [$urls, $phones, $emails, $text];
    }

    private function getFirstMatches(array $matches): array
    {
        return array_map(fn (array $match) => $match[0], $matches);
    }
}
