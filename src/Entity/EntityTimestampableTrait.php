<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait EntityTimestampableTrait
{
    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable")
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdAt;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable")
     * @Gedmo\Timestampable(on="update")
     */
    protected $updatedAt;

    public function setCreatedAt(\DateTimeImmutable $updatedAt)
    {
        $this->createdAt = $updatedAt;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }
}
