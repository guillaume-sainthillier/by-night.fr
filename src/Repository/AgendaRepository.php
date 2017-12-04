<?php

namespace App\Repository;

use App\Entity\City;
use App\Entity\Place;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Site;
use App\Entity\Agenda;
use App\Entity\User;
use App\Search\SearchAgenda;
use Doctrine\ORM\QueryBuilder;

class AgendaRepository extends EntityRepository
{
    public function createQueryBuilder($alias, $indexBy = null)
    {
        $qb = parent::createQueryBuilder($alias, $indexBy);

        return $qb->select($alias, 'p')
            ->addSelect('c')
            ->join($alias . '.place', 'p')
            ->join('p.city', 'c')
            ;
    }

    public function createElasticaQueryBuilder($alias, $indexBy = null)
    {
        $qb = $this->createQueryBuilder($alias, $indexBy);

        return $qb
            ->addSelect('zc')
            ->addSelect('c2')
            ->addSelect('c3')
            ->leftJoin('p.zipCity', 'zc')
            ->leftJoin('c.parent', 'c2')
            ->join('p.country', 'c3');
    }

    public function createIsActiveQueryBuilder()
    {
        $from = new \DateTime();
        $to   = new \DateTime();

        $from->modify(Agenda::INDEX_FROM);
        $to->modify(Agenda::INDEX_TO);

        $qb = $this->createElasticaQueryBuilder('a');

        return $qb
            ->where('a.dateFin >= :from')
            ->andWhere('a.dateFin <= :to')
            ->setParameters([
                'from' => $from->format('Y-m-d'),
                'to'   => $to->format('Y-m-d'),
            ]);
    }

    public function findSiteMap()
    {
        return $this->createQueryBuilder('a')
            ->select('a.slug, a.id, s.subdomain')
            ->leftJoin('App:Site', 's', 'WITH', 's = a.site')
            ->getQuery()
            ->getArrayResult();
    }

    public function updateNonIndexables()
    {
        $from = new \DateTime();
        $to   = new \DateTime();

        $from->modify(Agenda::INDEX_FROM);
        $to->modify(Agenda::INDEX_TO);

        return $this->_em
            ->createQuery('UPDATE App:Agenda a
            SET a.isArchive = :archive
            WHERE (a.dateFin < :from OR a.dateFin > :to)
            AND a.isArchive IS NULL) ')
            ->setParameters([
                'archive' => true,
                'from'    => $from->format('Y-m-d'),
                'to'      => $to->format('Y-m-d'),
            ])
            ->execute();
    }

    public function findNonIndexablesBuilder()
    {
        $from = new \DateTime();
        $to   = new \DateTime();

        $from->modify(Agenda::INDEX_FROM);
        $to->modify(Agenda::INDEX_TO);

        return $this
            ->createElasticaQueryBuilder('a')
            ->where('a.isArchive IS NULL OR a.isArchive = :archive')
            ->andWhere('a.dateFin < :from OR a.dateFin > :to')
            ->setParameters([
                'archive' => false,
                'from'    => $from->format('Y-m-d'),
                'to'      => $to->format('Y-m-d'),
            ])
            ->addOrderBy('a.id');
    }

    public function findByInterval(\DateTime $from, \DateTime $to)
    {
        $cities = $this->_em->getRepository('App:City')->findTopPopulation(50);
        $events = [];
        foreach ($cities as $city) {
            /*
             * @var City $city
             */
            $events[$city->getName()] = $this
                ->createQueryBuilder('a')
                ->where('a.dateFin BETWEEN :debut AND :fin')
                ->andWhere('p.city = :city')
                ->orderBy('a.fbParticipations', 'DESC')
                ->setMaxResults(3)
                ->setParameters([
                    ':debut' => $from->format('Y-m-d'),
                    ':fin'   => $to->format('Y-m-d'),
                    ':city'  => $city->getSlug(),
                ])
                ->getQuery()
                ->getResult();
        }

        return $events;
    }

    public function findAllByUser(UserInterface $user)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameters(['user' => $user])
            ->orderBy('a.dateModification', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllFBOwnerIds()
    {
        $places = $this->_em
            ->createQueryBuilder()
            ->select('a.facebookOwnerId')
            ->from('App:Agenda', 'a')
            ->where('a.facebookOwnerId IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return \array_unique(\array_filter(\array_column($places, 'facebookOwnerId')));
    }

    public function findAllOfWeek()
    {
        $now = new \DateTime();

        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('App:Agenda', 'a')
            ->where('a.dateFin BETWEEN :date_debut AND :date_fin')
            ->andWhere('a.facebookEventId IS NOT NULL')
            ->setParameters([':date_debut' => $now->format('Y-m-d'), ':date_fin' => $now->add(new \DateInterval('P7D'))->format('Y-m-d')])
            ->getQuery()
            ->getResult();
    }

    public function getLastDateStatsUser(User $user)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('MAX(c.lastDate) as last_date')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:User', 'u', 'WITH', 'u = c.user')
            ->where('c.user = :user')
            ->setParameters([':user' => $user->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNextEvents(\DateTime $since, $page, $offset)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateFin >= :since')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->setFirstResult($page * $offset)
            ->setMaxResults($offset)
            ->getQuery()
            ->getResult();
    }

    public function getNextEventsFbIds(\DateTime $since)
    {
        $datas = $this->_em
            ->createQueryBuilder()
            ->select('DISTINCT(a.facebookEventId)')
            ->from('App:Agenda', 'a')
            ->where('a.dateFin >= :since')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->getQuery()
            ->getScalarResult();

        return \array_filter(\array_map('current', $datas));
    }

    public function getNextEventsCount(\DateTime $since)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('App:Agenda', 'a')
            ->where('a.dateFin >= :since')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStatsUser(User $user, \DateTime $dateFin, $groupByDay = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u) as nbEvents')
            ->addSelect('a.dateDebut')
            ->addSelect(($groupByDay ? 'SUBSTRING(a.dateDebut, 6, 5)' : 'SUBSTRING(a.dateDebut, 1, 7)') . ' as date_event')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:User', 'u', 'WITH', 'u = c.user')
            ->leftJoin('App:Agenda', 'a', 'WITH', 'a = c.agenda')
            ->where('c.user = :user')
            ->andWhere('a.dateDebut >= :date_fin')
            ->groupBy('date_event')
            ->setParameters([':user' => $user->getId(), 'date_fin' => $dateFin])
            ->getQuery()
            ->getScalarResult();
    }

    public function findAllPlaces(User $user, $limit = 5)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u) as nbEtablissements, p.nom')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:User', 'u', 'WITH', 'u = c.user')
            ->leftJoin('App:Agenda', 'a', 'WITH', 'a = c.agenda')
            ->leftJoin('App:Place', 'p', 'WITH', 'p = a.place')
            ->where('c.user = :user')
            ->groupBy('p.nom')
            ->orderBy('nbEtablissements', 'DESC')
            ->setParameters([':user' => $user->getId()])
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllNextEvents(User $user, $isNext = true, $page = 1, $limit = 3)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:Agenda', 'a', 'WITH', 'c.agenda = a')
            ->where('c.user = :user')
            ->andWhere('a.dateFin ' . ($isNext ? '>=' : '<') . ' :date_debut')
            ->orderBy('a.dateFin', $isNext ? 'ASC' : 'DESC')
            ->setParameters([':user' => $user->getId(), 'date_debut' => \date('Y-m-d')])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    protected function getCountAllParticipations(User $user, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:User', 'u', 'WITH', 'u = c.user')
            ->where('c.user = :user')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet') . ' = :vrai')
            ->setParameters([':user' => $user->getId(), 'vrai' => true])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLastDateTopSoiree(Site $site)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('MAX(c.lastDate) as last_date')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:Agenda', 'a', 'WITH', 'a = c.agenda')
            ->where('a.site = :site')
            ->setParameters([':site' => $site->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountParticipations(User $user)
    {
        return $this->getCountAllParticipations($user);
    }

    public function getCountInterets(User $user)
    {
        return $this->getCountAllParticipations($user, false);
    }

    protected function getCountTendances(Agenda $soiree, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:User', 'u', 'WITH', 'u = c.user')
            ->where('c.agenda = :agenda')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet') . ' = :vrai')
            ->setParameters([':agenda' => $soiree->getId(), 'vrai' => true])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountTendancesParticipation(Agenda $soiree)
    {
        return $this->getCountTendances($soiree);
    }

    public function getCountTendancesInterets(Agenda $soiree)
    {
        return $this->getCountTendances($soiree, false);
    }

    protected function findAllTendances(Agenda $soiree, $page, $limit, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('u')
            ->from('App:Calendrier', 'c')
            ->leftJoin('App:User', 'u', 'WITH', 'u = c.user')
            ->where('c.agenda = :agenda')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet') . ' = :vrai')
            ->setParameters([':agenda' => $soiree, 'vrai' => true])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllTendancesParticipations(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this->findAllTendances($soiree, $page, $limit);
    }

    public function findAllTendancesInterets(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this->findAllTendances($soiree, $page, $limit, false);
    }

    public function findAllSimilaires(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateDebut = :date_debut AND a.id != :id AND p.city = :city')
            ->orderBy('a.nom', 'ASC')
            ->setParameters([':date_debut' => $soiree->getDateDebut()->format('Y-m-d'), ':id' => $soiree->getId(), ':city' => $soiree->getPlace()->getCity()->getId()])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllNext(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateFin >= :date_fin AND a.id != :id AND a.place = :place')
            ->orderBy('a.dateFin', 'ASC')
            ->addOrderBy('a.fbParticipations', 'DESC')
            ->setParameters([':date_fin' => $soiree->getDateFin()->format('Y-m-d'), ':id' => $soiree->getId(), ':place' => $soiree->getPlace()->getId()])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllSimilairesCount(Agenda $soiree)
    {
        return $this
            ->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('a.dateDebut = :date_debut AND a.id != :id AND p.city = :city')
            ->setParameters([':date_debut' => $soiree->getDateDebut()->format('Y-m-d'), ':id' => $soiree->getId(), ':city' => $soiree->getPlace()->getCity()->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllNextCount(Agenda $soiree)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('App:Agenda', 'a')
            ->where('a.dateFin >= :date_fin AND a.id != :id AND a.place = :place')
            ->setParameters([':date_fin' => $soiree->getDateFin()->format('Y-m-d'), ':id' => $soiree->getId(), ':place' => $soiree->getPlace()->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopSoireeCount(City $city)
    {
        $du = new \DateTime();
        $au = new \DateTime('sunday this week');

        return $this
            ->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('p.city = :city')
            ->andWhere('a.dateFin BETWEEN :du AND :au')
            ->setParameters([':city' => $city->getId(), 'du' => $du->format('Y-m-d'), 'au' => $au->format('Y-m-d')])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopSoiree(City $city, $page = 1, $limit = 7)
    {
        $du = new \DateTime();
        $au = new \DateTime('sunday this week');

        $soirees = $this
            ->createQueryBuilder('a')
            ->where('p.city = :city')
            ->andWhere('a.dateFin BETWEEN :du AND :au')
            ->orderBy('a.fbParticipations', 'DESC')
            ->addOrderBy('a.fbInterets', 'DESC')
            ->setParameters([':city' => $city->getId(), 'du' => $du->format('Y-m-d'), 'au' => $au->format('Y-m-d')])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();

        \usort($soirees, function (Agenda $a, Agenda $b) {
            if ($a->getDateFin() === $b->getDateFin()) {
                return 0;
            }

            return $a->getDateFin() > $b->getDateFin() ? -1 : 1;
        });

        return $soirees;
    }

    protected function makeQuery(QueryBuilder $qb, City $city, SearchAgenda $search)
    {
        $params = [':city' => $city->getId()];
        $qb->where('p.city = :city');

        if (null !== $search->getDu()) {
            $params[':du'] = $search->getDu()->format('Y-m-d');
            if (null === $search->getAu()) {
                $qb->andWhere(':du BETWEEN a.dateDebut AND a.dateFin');
            } else {
                $qb->andWhere('((a.dateDebut BETWEEN :du AND :au) '
                    . 'OR (a.dateFin BETWEEN :du AND :au) '
                    . 'OR (:du BETWEEN a.dateDebut AND a.dateFin)'
                    . 'OR (:au BETWEEN a.dateDebut AND a.dateFin))');

                $params[':au'] = $search->getAu()->format('Y-m-d');
            }
        } else {
            $qb->andWhere('a.dateFin >= :now');
            $params[':now'] = (new \DateTime())->format('Y-m-d');
        }

        if (\count($search->getTerms()) > 0) {
            $qb->andWhere('(a.nom LIKE :mot_clefs OR a.descriptif LIKE :mot_clefs OR a.lieuNom LIKE :mot_clefs)');
            $params[':mot_clefs'] = '%' . $search->getTerm() . '%';
        }

        if (null !== $search->getTypeManifestation() && \count($search->getTypeManifestation()) > 0) {
            $qb->andWhere('a.typeManifestation IN(:type_manifesation)');
            $params[':type_manifesation'] = $search->getTypeManifestation();
        }
        if (null !== $search->getCommune() && \count($search->getCommune()) > 0) {
            $qb->andWhere('a.commune IN(:commune)');
            $params[':commune'] = $search->getCommune();
        }

        if (null !== $search->getLieux() && \count($search->getLieux()) > 0) {
            $qb->andWhere('a.lieuNom IN(:lieux)');
            $params[':lieux'] = $search->getLieux();
        }

        return $qb
            ->setParameters($params);
    }

    public function findCountWithSearch(City $city, SearchAgenda $search)
    {
        $qb = $this->_em
            ->createQueryBuilder()
            ->select('COUNT(a) as nombre')
            ->from('App:Agenda', 'a')
            ->leftJoin('App:Place', 'p', 'WITH', 'a.place = p');

        return
            $this->makeQuery($qb, $city, $search)
                ->getQuery()
                ->getSingleScalarResult();
    }

    public function findWithSearch(City $city, SearchAgenda $search, $page = 1, $limit = 15, $orderDesc = true)
    {
        $qb = $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('App:Agenda', 'a')
            ->orderBy('a.dateDebut', $orderDesc ? 'DESC' : 'ASC');

        $soirees = $this->makeQuery($qb, $city, $search);

        if (false !== $page && false !== $limit) {
            $soirees = $soirees
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        return $soirees
            ->getQuery()
            ->execute();
    }

    //Appelé par DoctrineEventParser
    public function findAllByDates(array $events, array $fbIds)
    {
        if (!\count($events)) {
            return [];
        }

        $params = [];
        $query  = $this
            ->createQueryBuilder('a');

        $i = 0;
        foreach ($events as $event) {
            ++$i;
            /**
             * @var Agenda
             */
            $where                   = "a.dateDebut = :date_debut_$i AND a.dateFin = :date_fin_$i";
            $params["date_debut_$i"] = $event->getDateDebut()->format('Y-m-d');
            $params["date_fin_$i"]   = $event->getDateFin()->format('Y-m-d');
            if ($event->getPlace() && $event->getPlace()->getCity()) {
                $where .= " AND p.city = :city_$i";
                $params["city_$i"] = $event->getPlace()->getCity()->getId();
            }
            $query->andWhere($where);
        }

        if (\count($fbIds) > 0) {
            $query->andWhere('a.facebookEventId NOT IN (:fbIds)');
            $params['fbIds'] = $fbIds;
        }

        return $query
            ->setParameters($params)
            ->getQuery()
            ->getResult();
    }

    //Appelé par AgendaParser
    public function findOneByPlace($lieuNom, \DateTime $dateDebut, \DateTime $dateFin)
    {
        $query = $this
            ->createQueryBuilder('a')
            ->where('p.nom = :nom')
            ->andWhere('a.dateDebut = :date_debut')
            ->andWhere('a.dateFin = :date_fin')
            ->setParameters([':nom' => $lieuNom, 'date_debut' => $dateDebut->format('Y-m-d'), 'date_fin' => $dateFin->format('Y-m-d')])
            ->getQuery()
            ->setMaxResults(1);

        try {
            $agenda = $query->getSingleResult();
        } catch (NoResultException $e) {
            $agenda = null;
        }

        return $agenda;
    }

    public function getEventsWithFBOwner(City $city)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.place.city = :city')
            ->andWhere("a.facebookOwnerId != ''")
            ->groupBy('a.facebookOwnerId')
            ->setParameters([':city' => $city->getId()])
            ->getQuery()
            ->execute();
    }

    /**
     * @param City $city
     *
     * @return Place[]
     */
    public function getAgendaPlaces(City $city)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('p')
            ->from('App:Agenda', 'a')
            ->leftJoin('App:Place', 'p', 'WITH', 'p = a.place')
            ->where('p.city = :city')
            ->andWhere("p.nom != ''")
            ->andWhere('a.dateDebut >= :today')
            ->groupBy('p.nom')
            ->orderBy('p.nom')
            ->setParameters([':city' => $city->getId(), 'today' => (new \DateTime())->format('Y-m-d')])
            ->getQuery()
            ->execute();
    }

    /**
     * @param City $city
     *
     * @return Place[]
     */
    public function getAgendaVilles(City $city)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('p')
            ->from('App:Agenda', 'a')
            ->leftJoin('App:Place', 'p', 'WITH', 'p = a.place')
            ->where('p.city = :city')
            ->andWhere("p.ville != ''")
            ->andWhere('a.dateDebut >= :today')
            ->groupBy('p.ville')
            ->orderBy('p.ville')
            ->setParameters([':city' => $city->getId(), 'today' => (new \DateTime())->format('Y-m-d')])
            ->getQuery()
            ->execute();
    }

    /**
     * @param City $city
     *
     * @return Agenda[]
     */
    public function getTypesEvenements(City $city)
    {
        $from = new \DateTime();
        $to   = new \DateTime();

        $from->modify(Agenda::INDEX_FROM);
        $to->modify(Agenda::INDEX_TO);

        $results = $this->_em
            ->createQueryBuilder()
            ->select('a.categorieManifestation')
            ->from('App:Agenda', 'a')
            ->join('a.place', 'p')
            ->where('p.city = :city')
            ->andWhere('a.dateFin >= :from')
            ->andWhere('a.dateFin <= :to')
            ->andWhere("a.categorieManifestation != ''")
            ->groupBy('a.categorieManifestation')
            ->orderBy('a.categorieManifestation', 'DESC')
            ->setParameters([
                'city' => $city->getId(),
                'from' => $from->format('Y-m-d'),
                'to'   => $to->format('Y-m-d'),
            ])
            ->getQuery()
            ->getArrayResult();

        return \array_map('current', $results);
    }
}
