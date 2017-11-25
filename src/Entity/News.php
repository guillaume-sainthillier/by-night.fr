<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * News.
 *
 * @ORM\Table(name="news")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\NewsRepository")
 */
class News
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_debut", type="date")
     */
    private $dateDebut;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_fin", type="date")
     */
    private $dateFin;

    /**
     * @var int
     *
     * @ORM\Column(name="numero_edition", type="integer", unique=true)
     */
    private $numeroEdition;

    /**
     * @var int
     *
     * @ORM\Column(name="wordpress_post_id", type="integer", unique=true)
     */
    private $wordpressPostId;

    /**
     * @var string
     *
     * @ORM\Column(name="tweet_post_id", type="string", length=256, nullable=true)
     */
    private $tweetPostId;

    /**
     * @var string
     *
     * @ORM\Column(name="fb_post_id", type="string", length=256, nullable=true)
     */
    private $fbPostId;

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
     * Set dateDebut.
     *
     * @param \DateTime $dateDebut
     *
     * @return News
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut.
     *
     * @return \DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set dateFin.
     *
     * @param \DateTime $dateFin
     *
     * @return News
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin.
     *
     * @return \DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set numeroEdition.
     *
     * @param int $numeroEdition
     *
     * @return News
     */
    public function setNumeroEdition($numeroEdition)
    {
        $this->numeroEdition = $numeroEdition;

        return $this;
    }

    /**
     * Get numeroEdition.
     *
     * @return int
     */
    public function getNumeroEdition()
    {
        return $this->numeroEdition;
    }

    /**
     * Set wordpressPostId.
     *
     * @param int $wordpressPostId
     *
     * @return News
     */
    public function setWordpressPostId($wordpressPostId)
    {
        $this->wordpressPostId = $wordpressPostId;

        return $this;
    }

    /**
     * Get wordpressPostId.
     *
     * @return int
     */
    public function getWordpressPostId()
    {
        return $this->wordpressPostId;
    }

    /**
     * Set tweetPostId.
     *
     * @param string $tweetPostId
     *
     * @return News
     */
    public function setTweetPostId($tweetPostId)
    {
        $this->tweetPostId = $tweetPostId;

        return $this;
    }

    /**
     * Get tweetPostId.
     *
     * @return string
     */
    public function getTweetPostId()
    {
        return $this->tweetPostId;
    }

    /**
     * Set fbPostId.
     *
     * @param string $fbPostId
     *
     * @return News
     */
    public function setFbPostId($fbPostId)
    {
        $this->fbPostId = $fbPostId;

        return $this;
    }

    /**
     * Get fbPostId.
     *
     * @return string
     */
    public function getFbPostId()
    {
        return $this->fbPostId;
    }
}
