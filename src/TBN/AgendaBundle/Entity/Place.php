<?php

namespace TBN\AgendaBundle\Entity;

use TBN\AgendaBundle\Entity\Ville;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Gedmo\Mapping\Annotation as Gedmo;
use \Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * Place
 *
 * @ORM\Table(name="Place", indexes={
 *   @ORM\Index(name="place_nom_idx", columns={"nom"}),
 *   @ORM\Index(name="place_slug_idx", columns={"slug"}),
 * })
 * @ORM\Entity(repositoryClass="TBN\AgendaBundle\Entity\PlaceRepository")
 * @ORM\HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 */
class Place
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
     * @ORM\Column(name="rue", type="string", length=255, nullable=true)
     * @Expose
     */
    private $rue;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float", nullable=true)
     */
    private $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float", nullable=true)
     */
    private $longitude;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255)
     * @Assert\NotBlank(message="Vous devez indiquer le lieu de votre événement")
     * @Expose
     */
    private $nom;

    /**
     * @var string
     * @Gedmo\Slug(fields={"nom"})
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
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
    * @ORM\OneToMany(targetEntity="TBN\AgendaBundle\Entity\Agenda", mappedBy="place")
    * @ORM\OrderBy({"dateModification" = "DESC"})
    */
    protected $evenements;
    
    /**
    * @ORM\ManyToOne(targetEntity="TBN\AgendaBundle\Entity\Ville", cascade={"persist"})
    * @ORM\JoinColumn(nullable=true)
    * @Expose
    * @Assert\Valid()
    */
    protected $ville;

    /**
    * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site")
    * @ORM\JoinColumn(nullable=false)
    */
    protected $site;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->evenements   = new ArrayCollection();
        $this->ville        = new Ville();
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
     * Set rue
     *
     * @param string $rue
     * @return Place
     */
    public function setRue($rue)
    {
        $this->rue = $rue;
    
        return $this;
    }

    /**
     * Get rue
     *
     * @return string 
     */
    public function getRue()
    {
        return $this->rue;
    }

    /**
     * Set latitude
     *
     * @param float $latitude
     * @return Place
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
    
        return $this;
    }

    /**
     * Get latitude
     *
     * @return float 
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param float $longitude
     * @return Place
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
    
        return $this;
    }

    /**
     * Get longitude
     *
     * @return float 
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set nom
     *
     * @param string $nom
     * @return Place
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
     * Set slug
     *
     * @param string $slug
     * @return Place
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
     * @return Place
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
     * @return Place
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
     * Add evenements
     *
     * @param \TBN\AgendaBundle\Entity\Agenda $evenements
     * @return Place
     */
    public function addEvenement(\TBN\AgendaBundle\Entity\Agenda $evenements)
    {
        $this->evenements[] = $evenements;
    
        return $this;
    }

    /**
     * Remove evenements
     *
     * @param \TBN\AgendaBundle\Entity\Agenda $evenements
     */
    public function removeEvenement(\TBN\AgendaBundle\Entity\Agenda $evenements)
    {
        $this->evenements->removeElement($evenements);
    }

    /**
     * Get evenements
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getEvenements()
    {
        return $this->evenements;
    }

    /**
     * Set site
     *
     * @param \TBN\MainBundle\Entity\Site $site
     * @return Place
     */
    public function setSite(\TBN\MainBundle\Entity\Site $site)
    {
        $this->site = $site;
        if($this->getVille())
        {
            $this->getVille()->setSite($site);
        }
        
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

    /**
     * Set ville
     *
     * @param \TBN\AgendaBundle\Entity\Ville $ville
     * @return Place
     */
    public function setVille(\TBN\AgendaBundle\Entity\Ville $ville = null)
    {
        $this->ville = $ville;
    
        return $this;
    }

    /**
     * Get ville
     *
     * @return \TBN\AgendaBundle\Entity\Ville 
     */
    public function getVille()
    {
        return $this->ville;
    }
}
