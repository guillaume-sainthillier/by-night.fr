<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityIdentityTrait
{
    /**
     * @var int|null
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Returns the primary key identifier.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}
