<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Utils\Util;

use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Description of Merger
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Cleaner {

    private $propertyAccessor;
    private $util;

    public function __construct(Util $util)
    {
        $this->util = $util;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function getCleanedEvent(Agenda $agenda)
    {
        if(!$agenda->getDateFin() instanceof \DateTime)
        {
            $agenda->setDateFin($agenda->getDateDebut());
        }

	if($agenda->getDateFin() < $agenda->getDateDebut())
	{
	    $lastDate = $agenda->getDateDebut();
	    $agenda->setDateDebut($agenda->getDateFin())->setDateFin($lastDate);
	}

        return $agenda->setNom($this->clean($agenda->getNom()) ?: null)
                ->setDescriptif($this->clean($agenda->getDescriptif()) ?: null)
        ;
    }

    public function getCleanedPlace(Place $place)
    {
        return $place->setNom($this->cleanNormalString($place->getNom()) ?: null)
                ->setRue($this->cleanNormalString($place->getRue()) ?: null)
                ->setLatitude($this->util->replaceNonNumericChars($place->getLatitude()) ?: null)
                ->setLongitude($this->util->replaceNonNumericChars($place->getLongitude()) ?: null)
        ;
    }

    public function getCleanedVille(Ville $ville)
    {
        return $ville->setNom($this->cleanPostalString($ville->getNom()) ?: null)
                ->setCodePostal($this->util->replaceNonNumericChars($ville->getCodePostal()) ?: null);
    }

    private function clean($string)
    {
        return trim($this->util->deleteMultipleSpaces($string));
    }

    private function cleanString($string, $delimiters = [])
    {
        $step1 = $this->util->utf8TitleCase($string);
        $step2 = $this->util->deleteMultipleSpaces($step1);
        $step3 = $this->util->deleteSpaceBetween($step2, $delimiters);

        return trim($step3);
    }

    private function cleanNormalString($string)
    {
        return $this->cleanString($string, '');
    }

    private function cleanPostalString($string)
    {
        return $this->cleanString($string, ['-']);
    }
}
