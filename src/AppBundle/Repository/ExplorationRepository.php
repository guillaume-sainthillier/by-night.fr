<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class ExplorationRepository extends EntityRepository
{
    public function findAllByFBIds(array $fb_ids)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('e')
            ->from('AppBundle:Exploration', 'e')
            ->where('e.id IN(:ids)')
            ->setParameter('ids', $fb_ids)
            ->getQuery()
            ->getResult();
    }
}
