<?php

namespace TBN\CommentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\UserBundle\Entity\User;

/**
 * Comment
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="TBN\CommentBundle\Repository\CommentRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Comment
{
    /**
     * @var integer
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
     */
    protected $commentaire;

    /**
     * @var boolean
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
     *
     * @var User 
     * 
     * @ORM\ManyToOne(targetEntity="TBN\UserBundle\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     *
     * @var Agenda 
     * 
     * @ORM\ManyToOne(targetEntity="TBN\AgendaBundle\Entity\Agenda", inversedBy="commentaires")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $agenda;
    
    /**
     *
     * @var Comment 
     * 
     * @ORM\ManyToOne(targetEntity="TBN\CommentBundle\Entity\Comment", inversedBy="reponses")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $parent;
    
    /**
     * @ORM\OneToMany(targetEntity="TBN\CommentBundle\Entity\Comment", mappedBy="parent",cascade={"persist", "remove"})
     * @ORM\OrderBy({"dateCreation" = "DESC"})
     */
    protected $reponses;

    public function __construct()
    {
        $this->reponses = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set commentaire
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
     * Get commentaire
     *
     * @return string 
     */
    public function getCommentaire()
    {
        return $this->commentaire;
    }

    /**
     * Set isApprouve
     *
     * @param boolean $isApprouve
     *
     * @return Comment
     */
    public function setApprouve($isApprouve)
    {
        $this->isApprouve = $isApprouve;
    
        return $this;
    }

    /**
     * Get isApprouve
     *
     * @return boolean 
     */
    public function isApprouve()
    {
        return $this->isApprouve;
    }

    /**
     * Set dateCreation
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
     * Get dateCreation
     *
     * @return \DateTime 
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set dateModification
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
     * Get dateModification
     *
     * @return \DateTime 
     */
    public function getDateModification()
    {
        return $this->dateModification;
    }


    /**
     * Set user
     *
     * @param \TBN\UserBundle\Entity\User $user
     *
     * @return Comment
     */
    public function setUser(\TBN\UserBundle\Entity\User $user)
    {
        $this->user = $user;
    
        return $this;
    }


    /**
     * Get user
     *
     * @return \TBN\UserBundle\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set agenda
     *
     * @param \TBN\AgendaBundle\Entity\Agenda $agenda
     *
     * @return Comment
     */
    public function setAgenda(\TBN\AgendaBundle\Entity\Agenda $agenda)
    {
        $this->agenda = $agenda;
    
        return $this;
    }

    /**
     * Get agenda
     *
     * @return \TBN\AgendaBundle\Entity\Agenda 
     */
    public function getAgenda()
    {
        return $this->agenda;
    }

    /**
     * Set parent
     *
     * @param \TBN\CommentBundle\Entity\Comment $parent
     *
     * @return Comment
     */
    public function setParent(\TBN\CommentBundle\Entity\Comment $parent)
    {
        $this->parent = $parent;
    
        return $this;
    }

    /**
     * Get parent
     *
     * @return \TBN\CommentBundle\Entity\Comment 
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add reponses
     *
     * @param \TBN\CommentBundle\Entity\Comment $reponses
     *
     * @return Comment
     */
    public function addReponse(\TBN\CommentBundle\Entity\Comment $reponses)
    {
        $this->reponses[] = $reponses;
    
        return $this;
    }

    /**
     * Remove reponses
     *
     * @param \TBN\CommentBundle\Entity\Comment $reponses
     */
    public function removeReponse(\TBN\CommentBundle\Entity\Comment $reponses)
    {
        $this->reponses->removeElement($reponses);
    }

    /**
     * Get reponses
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getReponses()
    {
        return $this->reponses;
    }
}
