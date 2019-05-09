<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFilter;

/**
 * Description of TBNExtension.
 *
 * @author guillaume
 */
class TweetExtension extends Extension
{
    public function getFilters()
    {
        return [
            new TwigFilter('tweet', [$this, 'tweet']),
        ];
    }

    public function tweet($tweet)
    {
        $linkified = '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@';
        $hashified = '/(^|[\n\s])#([^\s"\t\n\r<:]*)/is';
        $mentionified = '/(^|[\n\s])@([^\s"\t\n\r<:]*)/is';

        $prettyTweet = \preg_replace(
            array(
                $linkified,
                $hashified,
                $mentionified,
            ),
            array(
                '<a href="$1" class="link-tweet" target="_blank">$1</a>',
                '$1<a class="link-hashtag" href="https://twitter.com/search?q=%23$2&src=hash" target="_blank">#$2</a>',
                '$1<a class="link-mention" href="http://twitter.com/$2" target="_blank">@$2</a>',
            ),
            $tweet
        );

        return $prettyTweet;
    }
}
