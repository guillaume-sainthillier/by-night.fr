<?php

namespace TBN\AgendaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * Ville
 *
 * @ORM\Table(name="Ville", indexes={
 *   @ORM\Index(name="ville_nom_idx", columns={"nom"}),
 *   @ORM\Index(name="ville_slug_idx", columns={"slug"}),
 * })
 * @ORM\Entity(repositoryClass="TBN\AgendaBundle\Entity\VilleRepository")
 * @ExclusionPolicy("all")
 */
class Ville
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Expose
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255)
     * @Assert\NotBlank(message="Vous devez indiquer la ville de votre événement en saisissant l'adresse")
     * @Expose
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="code_postal", type="string", length=10, nullable=true)
     * @Expose
     */
    private $codePostal;

    /**
     * @var string
     * @Gedmo\Slug(fields={"nom", "codePostal"})
     * @ORM\Column(name="slug", type="string", length=255)
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="string", length=255, nullable=true)
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=255, nullable=true)
     */
    private $url;

    /**
    * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site")
    * @ORM\JoinColumn(nullable=false)
    */
    protected $site;


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
     * Set nom
     *
     * @param string $nom
     * @return Ville
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
    
        return $this;
    }

    /**
     * Get nom
     *
     * @return string 
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set codePostal
     *
     * @param string $codePostal
     * @return Ville
     */
    public function setCodePostal($codePostal)
    {
        $this->codePostal = $codePostal;
    
        return $this;
    }

    /**
     * Get codePostal
     *
     * @return string 
     */
    public function getCodePostal()
    {
        return $this->codePostal;
    }

    /**
     * Set slug
     *
     * @param string $slug
     * @return Ville
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    
        return $this;
    }

    /**
     * Get slug
     *
     * @return string 
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set path
     *
     * @param string $path
     * @return Ville
     */
    public function setPath($path)
    {
        $this->path = $path;
    
        return $this;
    }

    /**
     * Get path
     *
     * @return string 
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return Ville
     */
    public function setUrl($url)
    {
        $this->url = $url;
    
        return $this;
    }

    /**
     * Get url
     *
     * @return string 
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set site
     *
     * @param \TBN\MainBundle\Entity\Site $site
     * @return Ville
     */
    public function setSite(\TBN\MainBundle\Entity\Site $site)
    {
        $this->site = $site;
    
        return $this;
    }

    /**
     * Get site
     *
     * @return \TBN\MainBundle\Entity\Site 
     */
    public function getSite()
    {
        return $this->site;
    }
}
