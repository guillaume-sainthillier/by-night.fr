<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * City.
 *
 * @ORM\Table()
 * @ORM\Entity(readOnly=true)
 */
class Location
{
    /**
     * @var string
     * @ORM\Column(name="id", type="string", length=255)
     * @ORM\Id
     */
    protected $id;

    /**
     * @ORM\Column(name="value", type="json_array")
     */
    protected $values;

    /**
     * Set id.
     *
     * @param string $id
     *
     * @return Location
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set values.
     *
     * @param array $values
     *
     * @return Location
     */
    public function setValues($values)
    {
        $this->values = $values;

        return $this;
    }

    /**
     * Get values.
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }
}
