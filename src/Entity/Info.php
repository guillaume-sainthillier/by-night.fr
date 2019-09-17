<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Info.
 *
 * @ORM\Table(name="Info", indexes={
 *     @ORM\Index(name="recherche_info_idx", columns={"facebook_id", "twitter_id", "google_id"})
 * })
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"user": "UserInfo", "site": "SiteInfo"})
 * @ORM\Entity(repositoryClass="App\Repository\InfoRepository")
 */
abstract class Info
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $hasSeeTuto;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $hasSeeAskingSocial;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_id;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_access_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_token_secret;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_refresh_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_email;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_expires_in;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_nickname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_realname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $facebook_profile_picture;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_id;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_access_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_token_secret;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_refresh_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_email;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_expires_in;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_nickname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_realname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $google_profile_picture;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_id;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_access_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_token_secret;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_refresh_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_email;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_expires_in;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_nickname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_realname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $twitter_profile_picture;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_id;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_access_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_token_secret;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_refresh_token;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_email;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_expires_in;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_nickname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_realname;

    /**
     * @ORM\Column(length=255, nullable=true)
     */
    protected $eventbrite_profile_picture;

    public function __construct()
    {
        $this->hasSeeTuto = false;
        $this->hasSeeAskingSocial = false;
    }

    public function __toString()
    {
        return '#' . $this->id ?: '?';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHasSeeTuto(): ?bool
    {
        return $this->hasSeeTuto;
    }

    public function setHasSeeTuto(bool $hasSeeTuto): self
    {
        $this->hasSeeTuto = $hasSeeTuto;

        return $this;
    }

    public function getHasSeeAskingSocial(): ?bool
    {
        return $this->hasSeeAskingSocial;
    }

    public function setHasSeeAskingSocial(bool $hasSeeAskingSocial): self
    {
        $this->hasSeeAskingSocial = $hasSeeAskingSocial;

        return $this;
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

    public function getFacebookTokenSecret(): ?string
    {
        return $this->facebook_token_secret;
    }

    public function setFacebookTokenSecret(?string $facebook_token_secret): self
    {
        $this->facebook_token_secret = $facebook_token_secret;

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

    public function getFacebookExpiresIn(): ?string
    {
        return $this->facebook_expires_in;
    }

    public function setFacebookExpiresIn(?string $facebook_expires_in): self
    {
        $this->facebook_expires_in = $facebook_expires_in;

        return $this;
    }

    public function getFacebookNickname(): ?string
    {
        return $this->facebook_nickname;
    }

    public function setFacebookNickname(?string $facebook_nickname): self
    {
        $this->facebook_nickname = $facebook_nickname;

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

    public function getGoogleTokenSecret(): ?string
    {
        return $this->google_token_secret;
    }

    public function setGoogleTokenSecret(?string $google_token_secret): self
    {
        $this->google_token_secret = $google_token_secret;

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

    public function getGoogleExpiresIn(): ?string
    {
        return $this->google_expires_in;
    }

    public function setGoogleExpiresIn(?string $google_expires_in): self
    {
        $this->google_expires_in = $google_expires_in;

        return $this;
    }

    public function getGoogleNickname(): ?string
    {
        return $this->google_nickname;
    }

    public function setGoogleNickname(?string $google_nickname): self
    {
        $this->google_nickname = $google_nickname;

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

    public function getTwitterTokenSecret(): ?string
    {
        return $this->twitter_token_secret;
    }

    public function setTwitterTokenSecret(?string $twitter_token_secret): self
    {
        $this->twitter_token_secret = $twitter_token_secret;

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

    public function getTwitterExpiresIn(): ?string
    {
        return $this->twitter_expires_in;
    }

    public function setTwitterExpiresIn(?string $twitter_expires_in): self
    {
        $this->twitter_expires_in = $twitter_expires_in;

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

    public function getEventbriteId(): ?string
    {
        return $this->eventbrite_id;
    }

    public function setEventbriteId(?string $eventbrite_id): self
    {
        $this->eventbrite_id = $eventbrite_id;

        return $this;
    }

    public function getEventbriteAccessToken(): ?string
    {
        return $this->eventbrite_access_token;
    }

    public function setEventbriteAccessToken(?string $eventbrite_access_token): self
    {
        $this->eventbrite_access_token = $eventbrite_access_token;

        return $this;
    }

    public function getEventbriteTokenSecret(): ?string
    {
        return $this->eventbrite_token_secret;
    }

    public function setEventbriteTokenSecret(?string $eventbrite_token_secret): self
    {
        $this->eventbrite_token_secret = $eventbrite_token_secret;

        return $this;
    }

    public function getEventbriteRefreshToken(): ?string
    {
        return $this->eventbrite_refresh_token;
    }

    public function setEventbriteRefreshToken(?string $eventbrite_refresh_token): self
    {
        $this->eventbrite_refresh_token = $eventbrite_refresh_token;

        return $this;
    }

    public function getEventbriteEmail(): ?string
    {
        return $this->eventbrite_email;
    }

    public function setEventbriteEmail(?string $eventbrite_email): self
    {
        $this->eventbrite_email = $eventbrite_email;

        return $this;
    }

    public function getEventbriteExpiresIn(): ?string
    {
        return $this->eventbrite_expires_in;
    }

    public function setEventbriteExpiresIn(?string $eventbrite_expires_in): self
    {
        $this->eventbrite_expires_in = $eventbrite_expires_in;

        return $this;
    }

    public function getEventbriteNickname(): ?string
    {
        return $this->eventbrite_nickname;
    }

    public function setEventbriteNickname(?string $eventbrite_nickname): self
    {
        $this->eventbrite_nickname = $eventbrite_nickname;

        return $this;
    }

    public function getEventbriteRealname(): ?string
    {
        return $this->eventbrite_realname;
    }

    public function setEventbriteRealname(?string $eventbrite_realname): self
    {
        $this->eventbrite_realname = $eventbrite_realname;

        return $this;
    }

    public function getEventbriteProfilePicture(): ?string
    {
        return $this->eventbrite_profile_picture;
    }

    public function setEventbriteProfilePicture(?string $eventbrite_profile_picture): self
    {
        $this->eventbrite_profile_picture = $eventbrite_profile_picture;

        return $this;
    }
}
