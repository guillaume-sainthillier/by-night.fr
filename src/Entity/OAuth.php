<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\OAuthRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Index(name: 'recherche_info_idx', columns: ['facebook_id', 'twitter_id', 'google_id'])]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap(['user' => 'UserOAuth', 'site' => 'AppOAuth'])]
#[ORM\Entity(repositoryClass: OAuthRepository::class)]
abstract class OAuth implements Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebook_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebook_access_token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebook_refresh_token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebook_email = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $facebook_expires = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebook_realname = null;

    #[ORM\Column(length: 511, nullable: true)]
    private ?string $facebook_profile_picture = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_access_token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_refresh_token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_email = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $google_expires = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_realname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_profile_picture = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_access_token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_refresh_token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_email = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $twitter_expires = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_nickname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_realname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_profile_picture = null;

    /**
     * Returns the primary key identifier.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return '#' . $this->id ?: '?';
    }

    public function getFacebookId(): ?string
    {
        return $this->facebook_id;
    }

    public function setFacebookId(?string $facebook_id): self
    {
        $this->facebook_id = $facebook_id;

        return $this;
    }

    public function getFacebookAccessToken(): ?string
    {
        return $this->facebook_access_token;
    }

    public function setFacebookAccessToken(?string $facebook_access_token): self
    {
        $this->facebook_access_token = $facebook_access_token;

        return $this;
    }

    public function getFacebookRefreshToken(): ?string
    {
        return $this->facebook_refresh_token;
    }

    public function setFacebookRefreshToken(?string $facebook_refresh_token): self
    {
        $this->facebook_refresh_token = $facebook_refresh_token;

        return $this;
    }

    public function getFacebookEmail(): ?string
    {
        return $this->facebook_email;
    }

    public function setFacebookEmail(?string $facebook_email): self
    {
        $this->facebook_email = $facebook_email;

        return $this;
    }

    public function getFacebookExpires(): ?int
    {
        return $this->facebook_expires;
    }

    public function setFacebookExpires(?int $facebook_expires): self
    {
        $this->facebook_expires = $facebook_expires;

        return $this;
    }

    public function getFacebookRealname(): ?string
    {
        return $this->facebook_realname;
    }

    public function setFacebookRealname(?string $facebook_realname): self
    {
        $this->facebook_realname = $facebook_realname;

        return $this;
    }

    public function getFacebookProfilePicture(): ?string
    {
        return $this->facebook_profile_picture;
    }

    public function setFacebookProfilePicture(?string $facebook_profile_picture): self
    {
        $this->facebook_profile_picture = $facebook_profile_picture;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->google_id;
    }

    public function setGoogleId(?string $google_id): self
    {
        $this->google_id = $google_id;

        return $this;
    }

    public function getGoogleAccessToken(): ?string
    {
        return $this->google_access_token;
    }

    public function setGoogleAccessToken(?string $google_access_token): self
    {
        $this->google_access_token = $google_access_token;

        return $this;
    }

    public function getGoogleRefreshToken(): ?string
    {
        return $this->google_refresh_token;
    }

    public function setGoogleRefreshToken(?string $google_refresh_token): self
    {
        $this->google_refresh_token = $google_refresh_token;

        return $this;
    }

    public function getGoogleEmail(): ?string
    {
        return $this->google_email;
    }

    public function setGoogleEmail(?string $google_email): self
    {
        $this->google_email = $google_email;

        return $this;
    }

    public function getGoogleExpires(): ?int
    {
        return $this->google_expires;
    }

    public function setGoogleExpires(?int $google_expires): self
    {
        $this->google_expires = $google_expires;

        return $this;
    }

    public function getGoogleRealname(): ?string
    {
        return $this->google_realname;
    }

    public function setGoogleRealname(?string $google_realname): self
    {
        $this->google_realname = $google_realname;

        return $this;
    }

    public function getGoogleProfilePicture(): ?string
    {
        return $this->google_profile_picture;
    }

    public function setGoogleProfilePicture(?string $google_profile_picture): self
    {
        $this->google_profile_picture = $google_profile_picture;

        return $this;
    }

    public function getTwitterId(): ?string
    {
        return $this->twitter_id;
    }

    public function setTwitterId(?string $twitter_id): self
    {
        $this->twitter_id = $twitter_id;

        return $this;
    }

    public function getTwitterAccessToken(): ?string
    {
        return $this->twitter_access_token;
    }

    public function setTwitterAccessToken(?string $twitter_access_token): self
    {
        $this->twitter_access_token = $twitter_access_token;

        return $this;
    }

    public function getTwitterRefreshToken(): ?string
    {
        return $this->twitter_refresh_token;
    }

    public function setTwitterRefreshToken(?string $twitter_refresh_token): self
    {
        $this->twitter_refresh_token = $twitter_refresh_token;

        return $this;
    }

    public function getTwitterEmail(): ?string
    {
        return $this->twitter_email;
    }

    public function setTwitterEmail(?string $twitter_email): self
    {
        $this->twitter_email = $twitter_email;

        return $this;
    }

    public function getTwitterExpires(): ?int
    {
        return $this->twitter_expires;
    }

    public function setTwitterExpires(?int $twitter_expires): self
    {
        $this->twitter_expires = $twitter_expires;

        return $this;
    }

    public function getTwitterNickname(): ?string
    {
        return $this->twitter_nickname;
    }

    public function setTwitterNickname(?string $twitter_nickname): self
    {
        $this->twitter_nickname = $twitter_nickname;

        return $this;
    }

    public function getTwitterRealname(): ?string
    {
        return $this->twitter_realname;
    }

    public function setTwitterRealname(?string $twitter_realname): self
    {
        $this->twitter_realname = $twitter_realname;

        return $this;
    }

    public function getTwitterProfilePicture(): ?string
    {
        return $this->twitter_profile_picture;
    }

    public function setTwitterProfilePicture(?string $twitter_profile_picture): self
    {
        $this->twitter_profile_picture = $twitter_profile_picture;

        return $this;
    }
}
