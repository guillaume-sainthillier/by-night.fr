<?php
namespace TBN\MajDataBundle\Repository;

use Doctrine\ORM\EntityRepository;

class ExplorationRepository extends EntityRepository
{
    public function findAllByFBIds(array $fb_ids)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('e')
            ->from('TBNMajDataBundle:Exploration', 'e')
            ->where('e.id IN(:ids)')
            ->setParameter('ids', $fb_ids)
            ->getQuery()
            ->getResult();
    }
}
