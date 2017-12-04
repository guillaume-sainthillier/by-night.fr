<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Info.
 *
 * @ORM\Table(name="Info",
 *             indexes={@ORM\Index(
 *                  name="recherche_info_idx",
 *                  columns={"facebook_id", "twitter_id", "google_id"}
 * )})
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"user" = "UserInfo", "site" = "SiteInfo"})
 * @ORM\Entity(repositoryClass="App\Repository\InfoRepository")
 */
abstract class Info
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var bool
     *
     * @ORM\Column(name="has_see_tuto", type="boolean")
     */
    protected $hasSeeTuto;

    /**
     * @var bool
     *
     * @ORM\Column(name="has_see_asking_social", type="boolean")
     */
    protected $hasSeeAskingSocial;

    /**
     * @ORM\Column(name="facebook_id", type="string", length=255, nullable=true)
     */
    protected $facebook_id;

    /**
     * @ORM\Column(name="facebook_access_token", type="string", length=255, nullable=true)
     */
    protected $facebook_access_token;

    /**
     * @ORM\Column(name="facebook_token_secret", type="string", length=255, nullable=true)
     */
    protected $facebook_token_secret;

    /**
     * @ORM\Column(name="facebook_refresh_token", type="string", length=255, nullable=true)
     */
    protected $facebook_refresh_token;
    /**
     * @ORM\Column(name="facebook_email", type="string", length=255, nullable=true)
     */
    protected $facebook_email;

    /**
     * @ORM\Column(name="facebook_expires_in", type="string", length=255, nullable=true)
     */
    protected $facebook_expires_in;

    /**
     * @ORM\Column(name="facebook_nickname", type="string", length=255, nullable=true)
     */
    protected $facebook_nickname;

    /**
     * @ORM\Column(name="facebook_realname", type="string", length=255, nullable=true)
     */
    protected $facebook_realname;

    /**
     * @ORM\Column(name="facebook_profile_picture", type="string", length=255, nullable=true)
     */
    protected $facebook_profile_picture;

    /**
     * @ORM\Column(name="google_id", type="string", length=255, nullable=true)
     */
    protected $google_id;

    /**
     * @ORM\Column(name="google_access_token", type="string", length=255, nullable=true)
     */
    protected $google_access_token;

    /**
     * @ORM\Column(name="google_token_secret", type="string", length=255, nullable=true)
     */
    protected $google_token_secret;

    /**
     * @ORM\Column(name="google_refresh_token", type="string", length=255, nullable=true)
     */
    protected $google_refresh_token;
    /**
     * @ORM\Column(name="google_email", type="string", length=255, nullable=true)
     */
    protected $google_email;

    /**
     * @ORM\Column(name="google_expires_in", type="string", length=255, nullable=true)
     */
    protected $google_expires_in;

    /**
     * @ORM\Column(name="google_nickname", type="string", length=255, nullable=true)
     */
    protected $google_nickname;

    /**
     * @ORM\Column(name="google_realname", type="string", length=255, nullable=true)
     */
    protected $google_realname;

    /**
     * @ORM\Column(name="google_profile_picture", type="string", length=255, nullable=true)
     */
    protected $google_profile_picture;

    /**
     * @ORM\Column(name="twitter_id", type="string", length=255, nullable=true)
     */
    protected $twitter_id;

    /**
     * @ORM\Column(name="twitter_access_token", type="string", length=255, nullable=true)
     */
    protected $twitter_access_token;

    /**
     * @ORM\Column(name="twitter_token_secret", type="string", length=255, nullable=true)
     */
    protected $twitter_token_secret;

    /**
     * @ORM\Column(name="twitter_refresh_token", type="string", length=255, nullable=true)
     */
    protected $twitter_refresh_token;

    /**
     * @ORM\Column(name="twitter_email", type="string", length=255, nullable=true)
     */
    protected $twitter_email;

    /**
     * @ORM\Column(name="twitter_expires_in", type="string", length=255, nullable=true)
     */
    protected $twitter_expires_in;

    /**
     * @ORM\Column(name="twitter_nickname", type="string", length=255, nullable=true)
     */
    protected $twitter_nickname;

    /**
     * @ORM\Column(name="twitter_realname", type="string", length=255, nullable=true)
     */
    protected $twitter_realname;

    /**
     * @ORM\Column(name="twitter_profile_picture", type="string", length=255, nullable=true)
     */
    protected $twitter_profile_picture;

    public function __construct()
    {
        $this->hasSeeTuto         = false;
        $this->hasSeeAskingSocial = false;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set hasSeeTuto.
     *
     * @param bool $hasSeeTuto
     *
     * @return Info
     */
    public function setHasSeeTuto($hasSeeTuto)
    {
        $this->hasSeeTuto = $hasSeeTuto;

        return $this;
    }

    /**
     * Get hasSeeTuto.
     *
     * @return bool
     */
    public function getHasSeeTuto()
    {
        return $this->hasSeeTuto;
    }

    /**
     * Set hasSeeAskingSocial.
     *
     * @param bool $hasSeeAskingSocial
     *
     * @return Info
     */
    public function setHasSeeAskingSocial($hasSeeAskingSocial)
    {
        $this->hasSeeAskingSocial = $hasSeeAskingSocial;

        return $this;
    }

    /**
     * Get hasSeeAskingSocial.
     *
     * @return bool
     */
    public function getHasSeeAskingSocial()
    {
        return $this->hasSeeAskingSocial;
    }

    /**
     * Set facebook_id.
     *
     * @param string $facebookId
     *
     * @return Info
     */
    public function setFacebookId($facebookId)
    {
        $this->facebook_id = $facebookId;

        return $this;
    }

    /**
     * Get facebook_id.
     *
     * @return string
     */
    public function getFacebookId()
    {
        return $this->facebook_id;
    }

    /**
     * Set facebook_access_token.
     *
     * @param string $facebookAccessToken
     *
     * @return Info
     */
    public function setFacebookAccessToken($facebookAccessToken)
    {
        $this->facebook_access_token = $facebookAccessToken;

        return $this;
    }

    /**
     * Get facebook_access_token.
     *
     * @return string
     */
    public function getFacebookAccessToken()
    {
        return $this->facebook_access_token;
    }

    /**
     * Set facebook_token_secret.
     *
     * @param string $facebookTokenSecret
     *
     * @return Info
     */
    public function setFacebookTokenSecret($facebookTokenSecret)
    {
        $this->facebook_token_secret = $facebookTokenSecret;

        return $this;
    }

    /**
     * Get facebook_token_secret.
     *
     * @return string
     */
    public function getFacebookTokenSecret()
    {
        return $this->facebook_token_secret;
    }

    /**
     * Set facebook_email.
     *
     * @param string $facebookEmail
     *
     * @return Info
     */
    public function setFacebookEmail($facebookEmail)
    {
        $this->facebook_email = $facebookEmail;

        return $this;
    }

    /**
     * Get facebook_email.
     *
     * @return string
     */
    public function getFacebookEmail()
    {
        return $this->facebook_email;
    }

    /**
     * Set facebook_nickname.
     *
     * @param string $facebookNickname
     *
     * @return Info
     */
    public function setFacebookNickname($facebookNickname)
    {
        $this->facebook_nickname = $facebookNickname;

        return $this;
    }

    /**
     * Get facebook_nickname.
     *
     * @return string
     */
    public function getFacebookNickname()
    {
        return $this->facebook_nickname;
    }

    /**
     * Set facebook_realname.
     *
     * @param string $facebookRealname
     *
     * @return Info
     */
    public function setFacebookRealname($facebookRealname)
    {
        $this->facebook_realname = $facebookRealname;

        return $this;
    }

    /**
     * Get facebook_realname.
     *
     * @return string
     */
    public function getFacebookRealname()
    {
        return $this->facebook_realname;
    }

    /**
     * Set facebook_profile_picture.
     *
     * @param string $facebookProfilePicture
     *
     * @return Info
     */
    public function setFacebookProfilePicture($facebookProfilePicture)
    {
        $this->facebook_profile_picture = $facebookProfilePicture;

        return $this;
    }

    /**
     * Get facebook_profile_picture.
     *
     * @return string
     */
    public function getFacebookProfilePicture()
    {
        return $this->facebook_profile_picture;
    }

    /**
     * Set google_id.
     *
     * @param string $googleId
     *
     * @return Info
     */
    public function setGoogleId($googleId)
    {
        $this->google_id = $googleId;

        return $this;
    }

    /**
     * Get google_id.
     *
     * @return string
     */
    public function getGoogleId()
    {
        return $this->google_id;
    }

    /**
     * Set google_access_token.
     *
     * @param string $googleAccessToken
     *
     * @return Info
     */
    public function setGoogleAccessToken($googleAccessToken)
    {
        $this->google_access_token = $googleAccessToken;

        return $this;
    }

    /**
     * Get google_access_token.
     *
     * @return string
     */
    public function getGoogleAccessToken()
    {
        return $this->google_access_token;
    }

    /**
     * Set google_token_secret.
     *
     * @param string $googleTokenSecret
     *
     * @return Info
     */
    public function setGoogleTokenSecret($googleTokenSecret)
    {
        $this->google_token_secret = $googleTokenSecret;

        return $this;
    }

    /**
     * Get google_token_secret.
     *
     * @return string
     */
    public function getGoogleTokenSecret()
    {
        return $this->google_token_secret;
    }

    /**
     * Set google_email.
     *
     * @param string $googleEmail
     *
     * @return Info
     */
    public function setGoogleEmail($googleEmail)
    {
        $this->google_email = $googleEmail;

        return $this;
    }

    /**
     * Get google_email.
     *
     * @return string
     */
    public function getGoogleEmail()
    {
        return $this->google_email;
    }

    /**
     * Set google_nickname.
     *
     * @param string $googleNickname
     *
     * @return Info
     */
    public function setGoogleNickname($googleNickname)
    {
        $this->google_nickname = $googleNickname;

        return $this;
    }

    /**
     * Get google_nickname.
     *
     * @return string
     */
    public function getGoogleNickname()
    {
        return $this->google_nickname;
    }

    /**
     * Set google_realname.
     *
     * @param string $googleRealname
     *
     * @return Info
     */
    public function setGoogleRealname($googleRealname)
    {
        $this->google_realname = $googleRealname;

        return $this;
    }

    /**
     * Get google_realname.
     *
     * @return string
     */
    public function getGoogleRealname()
    {
        return $this->google_realname;
    }

    /**
     * Set google_profile_picture.
     *
     * @param string $googleProfilePicture
     *
     * @return Info
     */
    public function setGoogleProfilePicture($googleProfilePicture)
    {
        $this->google_profile_picture = $googleProfilePicture;

        return $this;
    }

    /**
     * Get google_profile_picture.
     *
     * @return string
     */
    public function getGoogleProfilePicture()
    {
        return $this->google_profile_picture;
    }

    /**
     * Set twitter_id.
     *
     * @param string $twitterId
     *
     * @return Info
     */
    public function setTwitterId($twitterId)
    {
        $this->twitter_id = $twitterId;

        return $this;
    }

    /**
     * Get twitter_id.
     *
     * @return string
     */
    public function getTwitterId()
    {
        return $this->twitter_id;
    }

    /**
     * Set twitter_access_token.
     *
     * @param string $twitterAccessToken
     *
     * @return Info
     */
    public function setTwitterAccessToken($twitterAccessToken)
    {
        $this->twitter_access_token = $twitterAccessToken;

        return $this;
    }

    /**
     * Get twitter_access_token.
     *
     * @return string
     */
    public function getTwitterAccessToken()
    {
        return $this->twitter_access_token;
    }

    /**
     * Set twitter_token_secret.
     *
     * @param string $twitterTokenSecret
     *
     * @return Info
     */
    public function setTwitterTokenSecret($twitterTokenSecret)
    {
        $this->twitter_token_secret = $twitterTokenSecret;

        return $this;
    }

    /**
     * Get twitter_token_secret.
     *
     * @return string
     */
    public function getTwitterTokenSecret()
    {
        return $this->twitter_token_secret;
    }

    /**
     * Set twitter_email.
     *
     * @param string $twitterEmail
     *
     * @return Info
     */
    public function setTwitterEmail($twitterEmail)
    {
        $this->twitter_email = $twitterEmail;

        return $this;
    }

    /**
     * Get twitter_email.
     *
     * @return string
     */
    public function getTwitterEmail()
    {
        return $this->twitter_email;
    }

    /**
     * Set twitter_nickname.
     *
     * @param string $twitterNickname
     *
     * @return Info
     */
    public function setTwitterNickname($twitterNickname)
    {
        $this->twitter_nickname = $twitterNickname;

        return $this;
    }

    /**
     * Get twitter_nickname.
     *
     * @return string
     */
    public function getTwitterNickname()
    {
        return $this->twitter_nickname;
    }

    /**
     * Set twitter_realname.
     *
     * @param string $twitterRealname
     *
     * @return Info
     */
    public function setTwitterRealname($twitterRealname)
    {
        $this->twitter_realname = $twitterRealname;

        return $this;
    }

    /**
     * Get twitter_realname.
     *
     * @return string
     */
    public function getTwitterRealname()
    {
        return $this->twitter_realname;
    }

    /**
     * Set twitter_profile_picture.
     *
     * @param string $twitterProfilePicture
     *
     * @return Info
     */
    public function setTwitterProfilePicture($twitterProfilePicture)
    {
        $this->twitter_profile_picture = $twitterProfilePicture;

        return $this;
    }

    /**
     * Get twitter_profile_picture.
     *
     * @return string
     */
    public function getTwitterProfilePicture()
    {
        return $this->twitter_profile_picture;
    }

    /**
     * Set facebook_expires_in.
     *
     * @param string $facebookExpiresIn
     *
     * @return Info
     */
    public function setFacebookExpiresIn($facebookExpiresIn)
    {
        $this->facebook_expires_in = $facebookExpiresIn;

        return $this;
    }

    /**
     * Get facebook_expires_in.
     *
     * @return string
     */
    public function getFacebookExpiresIn()
    {
        return $this->facebook_expires_in;
    }

    /**
     * Set google_expires_in.
     *
     * @param string $googleExpiresIn
     *
     * @return Info
     */
    public function setGoogleExpiresIn($googleExpiresIn)
    {
        $this->google_expires_in = $googleExpiresIn;

        return $this;
    }

    /**
     * Get google_expires_in.
     *
     * @return string
     */
    public function getGoogleExpiresIn()
    {
        return $this->google_expires_in;
    }

    /**
     * Set twitter_expires_in.
     *
     * @param string $twitterExpiresIn
     *
     * @return Info
     */
    public function setTwitterExpiresIn($twitterExpiresIn)
    {
        $this->twitter_expires_in = $twitterExpiresIn;

        return $this;
    }

    /**
     * Get twitter_expires_in.
     *
     * @return string
     */
    public function getTwitterExpiresIn()
    {
        return $this->twitter_expires_in;
    }

    /**
     * Set facebook_refresh_token.
     *
     * @param string $facebookRefreshToken
     *
     * @return Info
     */
    public function setFacebookRefreshToken($facebookRefreshToken)
    {
        $this->facebook_refresh_token = $facebookRefreshToken;

        return $this;
    }

    /**
     * Get facebook_refresh_token.
     *
     * @return string
     */
    public function getFacebookRefreshToken()
    {
        return $this->facebook_refresh_token;
    }

    /**
     * Set google_refresh_token.
     *
     * @param string $googleRefreshToken
     *
     * @return Info
     */
    public function setGoogleRefreshToken($googleRefreshToken)
    {
        $this->google_refresh_token = $googleRefreshToken;

        return $this;
    }

    /**
     * Get google_refresh_token.
     *
     * @return string
     */
    public function getGoogleRefreshToken()
    {
        return $this->google_refresh_token;
    }

    /**
     * Set twitter_refresh_token.
     *
     * @param string $twitterRefreshToken
     *
     * @return Info
     */
    public function setTwitterRefreshToken($twitterRefreshToken)
    {
        $this->twitter_refresh_token = $twitterRefreshToken;

        return $this;
    }

    /**
     * Get twitter_refresh_token.
     *
     * @return string
     */
    public function getTwitterRefreshToken()
    {
        return $this->twitter_refresh_token;
    }

    public function __toString()
    {
        return '#' . $this->id ?: '?';
    }
}
