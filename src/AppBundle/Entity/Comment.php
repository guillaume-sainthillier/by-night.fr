<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Comment.
 *
 * @ORM\Table(name="Comment")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CommentRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Comment
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="commentaire", type="text")
     * @Assert\Length(min="3", minMessage="Le commentaire doit faire au moins {{ limit }} caractères")
     * @Assert\NotBlank(message="Le commentaire ne peut pas être vide")
     */
    protected $commentaire;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_approuve", type="boolean")
     */
    protected $isApprouve;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_creation", type="datetime")
     */
    protected $dateCreation;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_modification", type="datetime")
     */
    protected $dateModification;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     * @var Agenda
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Agenda", inversedBy="commentaires")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $agenda;

    /**
     * @var Comment
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Comment", inversedBy="reponses")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="parent", cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"dateCreation" = "DESC"})
     */
    protected $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->setDateCreation(new \DateTime());
        $this->setDateModification(new \DateTime());

        $this->setApprouve(true);
    }

    /**
     * @ORM\PreUpdate
     */
    public function setUpdateDate()
    {
        $this->setDateModification(new \DateTime());
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

    /**
     * Set dateCreation.
     *
     * @param \DateTime $dateCreation
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
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set dateModification.
     *
     * @param \DateTime $dateModification
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
     * @return \DateTime
     */
    public function getDateModification()
    {
        return $this->dateModification;
    }

    /**
     * Set user.
     *
     * @param User $user
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
     * @return \AppBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set agenda.
     *
     * @param Agenda $agenda
     *
     * @return Comment
     */
    public function setAgenda(Agenda $agenda)
    {
        $this->agenda = $agenda;

        return $this;
    }

    /**
     * Get agenda.
     *
     * @return \AppBundle\Entity\Agenda
     */
    public function getAgenda()
    {
        return $this->agenda;
    }

    /**
     * Set parent.
     *
     * @param Comment $parent
     *
     * @return Comment
     */
    public function setParent(Comment $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \AppBundle\Entity\Comment
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add reponses.
     *
     * @param Comment $reponses
     *
     * @return Comment
     */
    public function addReponse(Comment $reponses)
    {
        $this->reponses[] = $reponses;

        return $this;
    }

    /**
     * Remove reponses.
     *
     * @param Comment $reponses
     */
    public function removeReponse(Comment $reponses)
    {
        $this->reponses->removeElement($reponses);
    }

    /**
     * Get reponses.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReponses()
    {
        return $this->reponses;
    }

    public function __toString()
    {
        return sprintf('#%s', $this->id ?: '?');
    }

    /**
     * Set isApprouve.
     *
     * @param bool $isApprouve
     *
     * @return Comment
     */
    public function setIsApprouve($isApprouve)
    {
        $this->isApprouve = $isApprouve;

        return $this;
    }

    /**
     * Get isApprouve.
     *
     * @return bool
     */
    public function getIsApprouve()
    {
        return $this->isApprouve;
    }
}
