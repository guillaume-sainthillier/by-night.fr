<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\Entity\User;
use App\Picture\UserProfilePicture;
use App\Utils\HtmlFormatter;
use DateTimeInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The page of a member, as Google reads the profile pages of a community: the member it is about.
 */
final readonly class ProfilePageJsonLd
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserProfilePicture $userProfilePicture,
        private HtmlFormatter $htmlFormatter,
    ) {
    }

    public function generateProfilePageJsonLd(User $user): string
    {
        $person = [
            '@type' => 'Person',
            'name' => $user->getUsername(),
            'identifier' => (string) $user->getId(),
            'url' => $this->urlGenerator->generate('app_user_index', [
                'id' => $user->getId(),
                'slug' => $user->getSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        if ($user->getDescription()) {
            $person['description'] = $user->getDescription();
        }

        // A photo the member sent: the placeholder is no picture of them, and the old Facebook links no longer answer
        if ('upload' === $this->userProfilePicture->getPicturePathAndSource($user)['source']) {
            $person['image'] = $this->userProfilePicture->getOriginalProfilePicture($user);
        }

        $website = $this->htmlFormatter->ensureProtocol($user->getWebsite());
        if (null !== $website) {
            $person['sameAs'] = [$website];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'mainEntity' => $person,
        ];

        if ($user->getCreatedAt() instanceof DateTimeInterface) {
            $schema['dateCreated'] = $user->getCreatedAt()->format(DateTimeInterface::ATOM);
        }

        // A member writes their own name and description: invalid bytes become U+FFFD instead of a 500
        return json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
