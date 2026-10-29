<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use function Symfony\Component\String\u;

/**
 * The spam of the site's user content, whoever posts it: the events of the sources and of the members (the Firewall),
 * the comments (NotSpamComment). Both the words and the text are compared folded to lower-case ASCII, with single
 * spaces: « PRÊT », « pret » and « prêt » are one word to a spammer, who writes all three.
 */
final class SpamDetector
{
    private const array BLACK_LIST = [
        'Buy && sell tickets at', 'Please join', 'Invite Friends', 'Buy Tickets',
        'Find Local Concerts', 'reverbnation.com', 'pastaparty.com', 'evrd.us',
        'farishams.com', 'ty-segall.com',
        'Online-Streaming', 'DvdRip', 'Online HD Movies', 'FULL Movie Online Free', 'DvdRip-USA',
        '4K.Downloads', 'Super.4K.Videos', 'Free Trial Access',
        'Streaming Vf', 'vostfr', 'Film Streaming', 'Film Françaais',
        'Films VF', 'Film gratuit en streaming', 'HD-TV', 'DVD-Rip', 'VOSTFRdotCC!',
        'join the Illuminati', 'anti-breeze financial institution', 'call on +', 'Call Or Whats App On',
        'fritzkalkbrenner.com', 'campusfm.fr', 'polyamour.info', 'parislanuit.fr',
        'Please find the agenda', 'Fore More Details like our Page & Massage us',
        'storiesdown.com', 'view Instagram stories',
        'RETOUR AFFECTIF', "retour d'amour", 'Retour d’affection',
        'grand marabout', 'ENVOÛTEMENT AMOUREUX', 'faire revenir un homme', "rituel d'amour", 'Retour de l’être aimé',
        'valise magique',
    ];

    /**
     * Loan scams, posted as events by the sources (OpenAgenda, Facebook) and by members, and as comments: the same few
     * phrasings, in every number. « Offre de printemps » or « prêt de matériel entre voisins » are not one.
     */
    private const array LOAN_SCAM_PATTERNS = [
        '/\b(?:offres?|octrois?|propositions?) de (?:prets?|credits?|financements?)\b/',
        "/\\b(?:prets?|credits?|financements?) (?:d'argent )?entre particuliers?\\b/",
        '/\bpreteu(?:r|se)s?\b/',
        '/\btemoignages? de prets?\b/',
    ];

    /**
     * What only a comment is turned down for: an event's description may well name a « grand maître » or look for « un
     * financement », and lists its contacts.
     */
    private const array COMMENT_SCAM_PATTERNS = [
        "/\\brecherche d'un (?:pret|credit|financement)\\b/",
        '/\b(?:puissant|grand) (?:maitre|marabout|medium|voyant)\b/',
        '/\billuminati/',
        '/\badopte[rz]? (?:un |des )?bebes?\b/',
        '/\bmagie (?:africaine|pour gagner)\b/',
    ];

    /**
     * The topics of the comment scams (loans, mediums and spells): with a way to reach their author (an e-mail
     * address, a phone number, WhatsApp), a scam whatever the phrasing.
     */
    private const array COMMENT_SCAM_TOPICS = [
        '/\b(?:prets?|credits?|financements?|emprunts?|loans?|\w*kredit\w*)\b/',
        '/\b(?:mediums?|marabouts?|voyants?|voyance|magie|sortileges?|envoutements?|rituels?)\b/',
    ];

    private const string CONTACT = '/[a-z0-9._-]+ ?@ ?[a-z0-9-]+\.[a-z]{2,}|whats?s?app|\+?\d(?:[ .-]?\d){8,}/';

    /** @var list<string>|null BLACK_LIST folded, once */
    private static ?array $blackList = null;

    /**
     * Spam in an event's name or description, or in a comment.
     */
    public static function isSpam(?string $content): bool
    {
        return self::isFoldedSpam(self::fold($content ?? ''));
    }

    /**
     * Spam in a comment: what isSpam() finds, and the scams only a comment is suspected of.
     */
    public static function isSpamComment(?string $comment): bool
    {
        $comment = self::fold($comment ?? '');

        return self::isFoldedSpam($comment)
            || array_any(self::COMMENT_SCAM_PATTERNS, static fn (string $pattern): bool => 1 === preg_match($pattern, $comment))
            || (1 === preg_match(self::CONTACT, $comment)
                && array_any(self::COMMENT_SCAM_TOPICS, static fn (string $pattern): bool => 1 === preg_match($pattern, $comment)));
    }

    private static function isFoldedSpam(string $content): bool
    {
        self::$blackList ??= array_map(self::fold(...), self::BLACK_LIST);

        return array_any(self::$blackList, static fn (string $blackWord): bool => str_contains($content, $blackWord))
            || array_any(self::LOAN_SCAM_PATTERNS, static fn (string $pattern): bool => 1 === preg_match($pattern, $content));
    }

    private static function fold(string $text): string
    {
        return u($text)->ascii()->lower()->collapseWhitespace()->toString();
    }
}
