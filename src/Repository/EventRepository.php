<?php

namespace App\Repository;

use App\App\Location;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\User\UserInterface;

class EventRepository extends EntityRepository
{
    public function createQueryBuilder($alias, $indexBy = null)
    {
        $qb = parent::createQueryBuilder($alias, $indexBy);

        return $qb->select($alias, 'p')
            ->addSelect('c')
            ->addSelect('c2')
            ->join($alias . '.place', 'p')
            ->leftJoin('p.city', 'c')
            ->leftJoin('c.parent', 'c2');
    }

    public function createElasticaQueryBuilder($alias, $indexBy = null)
    {
        return $this
            ->createQueryBuilder($alias, $indexBy)
            ->addSelect('c3')
            ->join('p.country', 'c3');
    }

    public function createIsActiveQueryBuilder()
    {
        $from = new DateTime();
        $from->modify(Event::INDEX_FROM);

        $to = new DateTime();
        $to->modify(Event::INDEX_TO);

        $qb = $this->createElasticaQueryBuilder('a');

        return $qb
            ->where('a.dateFin BETWEEN :from AND :to')
            ->setParameters([
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ]);
    }

    public function findSiteMap()
    {
        return $this->createQueryBuilder('a')
            ->select('a.slug, a.id, c.slug AS city_slug')
            ->getQuery()
            ->iterate();
    }

    public function updateNonIndexables()
    {
        $from = new DateTime();
        $to = new DateTime();

        $from->modify(Event::INDEX_FROM);
        $to->modify(Event::INDEX_TO);

        return $this->_em
            ->createQuery('UPDATE App:Event a
            SET a.isArchive = :archive
            WHERE (a.dateFin < :from OR a.dateFin > :to)
            AND a.isArchive = false')
            ->setParameters([
                'archive' => true,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ])
            ->execute();
    }

    public function findNonIndexablesBuilder()
    {
        $from = new DateTime();
        $to = new DateTime();

        $from->modify(Event::INDEX_FROM);
        $to->modify(Event::INDEX_TO);

        return $this
            ->createElasticaQueryBuilder('a')
            ->where('a.isArchive = false')
            ->andWhere('a.dateFin < :from OR a.dateFin > :to')
            ->setParameters([
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ])
            ->addOrderBy('a.id');
    }

    public function findByInterval(DateTime $from, DateTime $to)
    {
        $cities = $this->_em->getRepository(City::class)->findTopPopulation(50);
        $events = [];
        foreach ($cities as $city) {
            /** @var City $city */
            $events[$city->getName()] = $this
                ->createQueryBuilder('a')
                ->where('a.dateFin BETWEEN :debut AND :fin')
                ->andWhere('p.city = :city')
                ->orderBy('a.fbParticipations', 'DESC')
                ->setMaxResults(3)
                ->setParameters([
                    ':debut' => $from->format('Y-m-d'),
                    ':fin' => $to->format('Y-m-d'),
                    ':city' => $city->getSlug(),
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
            ->from('App:Event', 'a')
            ->where('a.facebookOwnerId IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return \array_unique(\array_filter(\array_column($places, 'facebookOwnerId')));
    }

    public function getCountryEvents()
    {
        $from = new DateTime();

        return $this->_em
            ->createQueryBuilder()
            ->select('c.name, c.slug, COUNT(a.id) AS events')
            ->from('App:Event', 'a')
            ->join('a.place', 'p')
            ->join('p.country', 'c')
            ->where('a.dateDebut >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('events', 'DESC')
            ->groupBy('c.id')
            ->getQuery()
            ->getScalarResult();
    }

    public function findAllOfWeek()
    {
        $now = new DateTime();

        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('App:Event', 'a')
            ->where('a.dateFin BETWEEN :date_debut AND :date_fin')
            ->andWhere('a.facebookEventId IS NOT NULL')
            ->setParameters([':date_debut' => $now->format('Y-m-d'), ':date_fin' => $now->add(new DateInterval('P7D'))->format('Y-m-d')])
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

    public function getNextEvents(DateTime $since, $page, $offset)
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.dateFin >= :since')
            ->andWhere('a.facebookEventId IS NOT NULL')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->setFirstResult(($page - 1) * $offset)
            ->setMaxResults($offset)
            ->getQuery()
            ->getResult();
    }

    public function getNextEventsCount(DateTime $since)
    {
        return $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.dateFin >= :since')
            ->andWhere('a.facebookEventId IS NOT NULL')
            ->setParameters([':since' => $since->format('Y-m-d')])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStatsUser(User $user, $groupByFunction)
    {
        $datas = $this->_em
            ->createQueryBuilder()
            ->select(sprintf('%s(a.dateFin) as group', $groupByFunction))
            ->addSelect('count(a.id) as events')
            ->from($this->_entityName, 'a')
            ->join('a.calendriers', 'c')
            ->join('c.user', 'u')
            ->where('u.id = :user')
            ->setParameters([':user' => $user->getId()])
            ->groupBy('group')
            ->getQuery()
            ->getScalarResult();

        $ordered = [];
        foreach ($datas as $data) {
            $ordered[$data['group']] = (int) $data['events'];
        }

        return $ordered;
    }

    public function findAllPlaces(User $user, $limit = 5)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u) as nbEtablissements, p.nom')
            ->from('App:Calendrier', 'c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.event', 'a')
            ->join('a.place', 'p')
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
        return $this
            ->createQueryBuilder('a')
            ->join('a.calendriers', 'cal')
            ->where('cal.user = :user')
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
            ->leftJoin('c.user', 'u')
            ->where('c.user = :user')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet') . ' = :vrai')
            ->setParameters([':user' => $user->getId(), 'vrai' => true])
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

    protected function getCountTendances(Event $event, $isParticipation = true)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('COUNT(u)')
            ->from('App:Calendrier', 'c')
            ->leftJoin('c.user', 'u')
            ->where('c.event = :event')
            ->andWhere(($isParticipation ? 'c.participe' : 'c.interet') . ' = :vrai')
            ->setParameters([':event' => $event->getId(), 'vrai' => true])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountTendancesParticipation(Event $event)
    {
        return $this->getCountTendances($event);
    }

    public function getCountTendancesInterets(Event $event)
    {
        return $this->getCountTendances($event, false);
    }

    public function findAllTendances(Event $event, $page = 1, $limit = 7)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('u')
            ->addSelect('c')
            ->addSelect('COUNT(u.id) AS nb_events')
            ->from('App:User', 'u')
            ->join('u.calendriers', 'c')
            ->leftJoin('u.calendriers', 'c2')
            ->where('c.event = :event')
            ->orderBy('nb_events', 'DESC')
            ->groupBy('u.id')
            ->setParameters([':event' => $event->getId()])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    private function getFindAllSimilairesBuilder(Event $event)
    {
        $qb = $this
            ->createQueryBuilder('a')
            ->where('a.dateDebut = :from')
            ->andWhere('a.id != :id')
            ->setParameters([
                ':from' => $event->getDateDebut()->format('Y-m-d'),
                ':id' => $event->getId(),
            ]);

        if ($event->getPlace()->getCity()) {
            $qb
                ->andWhere('p.city = :city')
                ->setParameter('city', $event->getPlace()->getCity()->getId());
        } elseif ($event->getPlace()->getCountry()) {
            $qb
                ->andWhere('p.country = :country')
                ->setParameter('country', $event->getPlace()->getCountry()->getId());
        }

        return $qb;
    }

    public function findAllSimilaires(Event $event, $page = 1, $limit = 7)
    {
        return $this
            ->getFindAllSimilairesBuilder($event)
            ->orderBy('a.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllSimilairesCount(Event $event)
    {
        return $this
            ->getFindAllSimilairesBuilder($event)
            ->select('count(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllNext(Event $event, $page = 1, $limit = 7)
    {
        $from = new DateTime();

        return $this
            ->createQueryBuilder('a')
            ->where('a.dateFin >= :date_fin AND a.id != :id AND a.place = :place')
            ->orderBy('a.dateFin', 'ASC')
            ->addOrderBy('a.fbParticipations', 'DESC')
            ->setParameters([':date_fin' => $from->format('Y-m-d'), ':id' => $event->getId(), ':place' => $event->getPlace()->getId()])
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllNextCount(Event $event)
    {
        $from = new DateTime();

        return $this->_em
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from('App:Event', 'a')
            ->where('a.dateFin >= :date_fin AND a.id != :id AND a.place = :place')
            ->setParameters([':date_fin' => $from->format('Y-m-d'), ':id' => $event->getId(), ':place' => $event->getPlace()->getId()])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTopSoireeBuilder(Location $location)
    {
        $du = new DateTime();
        $au = new DateTime('sunday this week');

        $qb = $this
            ->createQueryBuilder('a')
            ->where('a.dateFin BETWEEN :from AND :to');

        if ($location->isCity()) {
            $qb
                ->andWhere('c.id = :city')
                ->setParameter('city', $location->getCity()->getId());
        } elseif ($location->isCountry()) {
            $qb
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        }

        return $qb
            ->setParameter('from', $du->format('Y-m-d'))
            ->setParameter('to', $au->format('Y-m-d'));
    }

    public function findTopSoireeCount(Location $location)
    {
        return $this
            ->getTopSoireeBuilder($location)
            ->select('count(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopSoiree(Location $location, $page = 1, $limit = 7)
    {
        $events = $this
            ->getTopSoireeBuilder($location)
            ->orderBy('a.fbParticipations', 'DESC')
            ->addOrderBy('a.fbInterets', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();

        \usort($events, function (Event $a, Event $b) {
            if ($a->getDateFin() === $b->getDateFin()) {
                return 0;
            }

            return $a->getDateFin() > $b->getDateFin() ? -1 : 1;
        });

        return $events;
    }

    private function buildLocationParameters(QueryBuilder $queryBuilder, Location $location)
    {
        if ($location->isCountry()) {
            $queryBuilder
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        } elseif ($location->isCity()) {
            $queryBuilder
                ->andWhere('p.city = :city')
                ->setParameter('city', $location->getCity()->getId());
        }

        return $queryBuilder;
    }

    public function findCountWithSearch(Location $location)
    {
        $from = new DateTime();

        $qb = $this
            ->createQueryBuilder('a')
            ->select('COUNT(a) as nombre')
            ->where('a.dateDebut >= :from')
            ->setParameter('from', $from->format('Y-m-d'));

        $this->buildLocationParameters($qb, $location);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    //Appelé par EventParser
    public function findOneByPlace($lieuNom, DateTime $dateDebut, DateTime $dateFin)
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
            $event = $query->getSingleResult();
        } catch (NoResultException $e) {
            $event = null;
        }

        return $event;
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
     * @return Place[]
     */
    public function getEventPlaces(Location $location)
    {
        $from = new DateTime();
        $from->modify(Event::INDEX_FROM);

        $to = new DateTime();
        $to->modify(Event::INDEX_TO);

        $qb = $this->_em
            ->createQueryBuilder()
            ->select('p')
            ->from('App:Event', 'a')
            ->join('App:Place', 'p', 'WITH', 'p = a.place')
            ->where("p.nom != ''")
            ->andWhere('a.dateFin BETWEEN :from AND :to');

        $this->buildLocationParameters($qb, $location);
        if ($location->isCountry()) {
            $qb->andWhere('p.city IS NULL');
        }

        return $qb
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->groupBy('p.nom')
            ->orderBy('p.nom')
            ->getQuery()
            ->execute();
    }

    /**
     * @return Event[]
     */
    public function getTypesEvenements(Location $location)
    {
        $from = new DateTime();
        $from->modify(Event::INDEX_FROM);

        $to = new DateTime();
        $to->modify(Event::INDEX_TO);

        $qb = $this->_em
            ->createQueryBuilder()
            ->select('a.categorieManifestation')
            ->from('App:Event', 'a')
            ->join('a.place', 'p')
            ->where("a.categorieManifestation != ''")
            ->andWhere('a.dateFin BETWEEN :from AND :to');

        if ($location->isCity()) {
            $qb->andWhere('p.city = :city')
                ->setParameter('city', $location->getCity()->getId());
        } elseif ($location->isCountry()) {
            $qb->andWhere('p.city IS NULL')
                ->andWhere('p.country = :country')
                ->setParameter('country', $location->getCountry()->getId());
        }

        $results = $qb
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->groupBy('a.categorieManifestation')
            ->orderBy('a.categorieManifestation', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return \array_map('current', $results);
    }
}
