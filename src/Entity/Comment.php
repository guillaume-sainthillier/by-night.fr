<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Comment.
 *
 * @ORM\Table(name="Comment")
 * @ORM\Entity(repositoryClass="App\Repository\CommentRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Comment
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     * @Assert\Length(min="3", minMessage="Le commentaire doit faire au moins {{ limit }} caractères")
     * @Assert\NotBlank(message="Le commentaire ne peut pas être vide")
     */
    protected $commentaire;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $isApprouve;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    protected $dateCreation;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    protected $dateModification;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     * @var Event
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Event", inversedBy="commentaires")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $event;

    /**
     * @var Comment
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Comment", inversedBy="reponses")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Comment", mappedBy="parent", cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"dateCreation": "DESC"})
     */
    protected $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->setDateCreation(new DateTime());
        $this->setDateModification(new DateTime());

        $this->setApprouve(true);
    }

    /**
     * @ORM\PreUpdate
     */
    public function setUpdateDate()
    {
        $this->setDateModification(new DateTime());
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
     * Set commentaire.
     *
     * @param string $commentaire
     *
     * @return Comment
     */
    public function setCommentaire($commentaire)
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * Get commentaire.
     *
     * @return string
     */
    public function getCommentaire()
    {
        return $this->commentaire;
    }

    /**
     * Set dateCreation.
     *
     * @param DateTime $dateCreation
     *
     * @return Comment
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation.
     *
     * @return DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set dateModification.
     *
     * @param DateTime $dateModification
     *
     * @return Comment
     */
    public function setDateModification($dateModification)
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    /**
     * Get dateModification.
     *
     * @return DateTime
     */
    public function getDateModification()
    {
        return $this->dateModification;
    }

    /**
     * Set user.
     *
     *
     * @return Comment
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set event.
     *
     *
     * @return Comment
     */
    public function setEvent(Event $event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Get event.
     *
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Set parent.
     *
     *
     * @return Comment
     */
    public function setParent(self $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return Comment
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add reponses.
     *
     *
     * @return Comment
     */
    public function addReponse(self $reponses)
    {
        $this->reponses[] = $reponses;

        return $this;
    }

    /**
     * Remove reponses.
     */
    public function removeReponse(self $reponses)
    {
        $this->reponses->removeElement($reponses);
    }

    /**
     * Get reponses.
     *
     * @return Collection
     */
    public function getReponses()
    {
        return $this->reponses;
    }

    public function __toString()
    {
        return \sprintf('#%s', $this->id ?: '?');
    }

    /**
     * Set isApprouve.
     *
     * @param bool $isApprouve
     *
     * @return Comment
     */
    public function setApprouve($isApprouve)
    {
        $this->isApprouve = $isApprouve;

        return $this;
    }

    /**
     * Get isApprouve.
     *
     * @return bool
     */
    public function isApprouve()
    {
        return $this->isApprouve;
    }
}
