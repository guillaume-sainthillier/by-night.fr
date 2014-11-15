<?php
namespace TBN\AgendaBundle\Repository;

use Doctrine\ORM\EntityRepository;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\UserBundle\Entity\User;
use TBN\AgendaBundle\Search\SearchAgenda;
use Doctrine\ORM\QueryBuilder;

class AgendaRepository extends EntityRepository{

    public function getLastDateStatsUser(User $user)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('MAX(c.lastDate) as last_date')
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNUserBundle:User", "u", "WITH", "u = c.user")
        ->where("c.user = :user")
        ->setParameters([":user" => $user->getId()])
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function getStatsUser(User $user, \DateTime $dateFin, $groupByDay = true)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('COUNT(u) as nbEvents')
        ->addSelect("a.dateDebut")
        ->addSelect(($groupByDay ? "SUBSTRING(a.dateDebut, 6, 5)" : "SUBSTRING(a.dateDebut, 1, 7)")." as date_event")
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNUserBundle:User", "u", "WITH", "u = c.user")
        ->leftJoin("TBNAgendaBundle:Agenda", "a", "WITH", "a = c.agenda")
        ->where("c.user = :user")
        ->andWhere("a.dateDebut >= :date_fin")
        ->groupBy("date_event")
        ->setParameters([":user" => $user->getId(), "date_fin" => $dateFin])
        ->getQuery()
        ->getScalarResult();
    }

    public function findAllEtablissements(User $user, $limit = 5)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('COUNT(u) as nbEtablissements, a.lieuNom')
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNUserBundle:User", "u", "WITH", "u = c.user")
        ->leftJoin("TBNAgendaBundle:Agenda", "a", "WITH", "a = c.agenda")
        ->where("c.user = :user")
        ->groupBy("a.lieuNom")
        ->orderBy("nbEtablissements", "DESC")
        ->setParameters([":user" => $user->getId()])
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
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNAgendaBundle:Agenda", "a", 'WITH', "c.agenda = a")
        ->where("c.user = :user")
        ->andWhere("a.dateDebut ".($isNext ? ">=" : "<")." :date_debut")
        ->orderBy("a.dateDebut", $isNext ? "ASC" : "DESC")
        ->setParameters([":user" => $user->getId(), "date_debut" => date("Y-m-d")])
        ->setFirstResult(($page-1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->execute();
    }

    protected function getCountAllParticipations(User $user, $isParticipation = true)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('COUNT(u)')
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNUserBundle:User", "u", "WITH", "u = c.user")
        ->where("c.user = :user")
        ->andWhere(($isParticipation ? "c.participe": "c.interet")." = :vrai")
        ->setParameters([":user" => $user->getId(), "vrai" => true])
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function getLastDateTopSoiree(Site $site)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('MAX(c.lastDate) as last_date')
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNAgendaBundle:Agenda", "a", "WITH", "a = c.agenda")
        ->where("a.site = :site")
        ->setParameters([":site" => $site->getId()])
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
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNUserBundle:User", "u", "WITH", "u = c.user")
        ->where("c.agenda = :agenda")
        ->andWhere(($isParticipation ? "c.participe": "c.interet")." = :vrai")
        ->setParameters([":agenda" => $soiree->getId(), "vrai" => true])
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
        ->from('TBNAgendaBundle:Calendrier',"c")
        ->leftJoin("TBNUserBundle:User", "u", "WITH", "u = c.user")
        ->where("c.agenda = :agenda")
        ->andWhere(($isParticipation ? "c.participe": "c.interet")." = :vrai")
        ->setParameters([":agenda" => $soiree, "vrai" => true])
        ->setFirstResult(($page-1) * $limit)
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

    public function getLastDateAutreSoirees(Agenda $soiree)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('MAX(a.dateModification)')
        ->from('TBNAgendaBundle:Agenda','a')
        ->where("a.dateDebut = :date_debut AND a.id != :id AND a.site = :site")
        ->setParameters([":date_debut" => $soiree->getDateDebut(), ":id" => $soiree->getId(), ":site" => $soiree->getSite()->getId()])
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function findAllSimilaires(Agenda $soiree, $page = 1, $limit = 7)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda','a')
        ->where("a.dateDebut = :date_debut AND a.id != :id AND a.site = :site")
        ->orderBy('a.nom','ASC')
        ->setParameters([":date_debut" => $soiree->getDateDebut(), ":id" => $soiree->getId(), ":site" => $soiree->getSite()->getId()])
        ->setFirstResult(($page-1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->execute();
    }

    public function findTopSoiree(Site $site, $page = 1, $limit = 7)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda',"a")
        ->where("a.site = :site")
        ->andWhere("a.dateDebut >= :du")        
        ->orderBy("a.fbParticipations", "DESC")
        ->addOrderBy("a.participations", "DESC")
        ->addOrderBy("a.interets", "DESC")
        ->addOrderBy("a.fbInterets", "DESC")
        ->setParameters([":site" => $site->getId(), "du" => \date("Y-m-d")])
        ->setFirstResult(($page-1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->execute();
    }

    public function findTopMembres(Site $site, $page = 1, $limit = 7)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('u')
        ->from('TBNUserBundle:User',"u")
        ->leftJoin("TBNAgendaBundle:Calendrier", "c", 'WITH', "c.user = u")
        ->where("u.site = :site")
        ->orderBy('u.lastLogin','DESC')
        ->setParameters([":site" => $site->getId()])
        ->setFirstResult(($page-1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->execute();
    }

    protected function makeQuery(QueryBuilder $qb, Site $site, SearchAgenda $search)
    {
        $params = [":site" => $site->getId()];
        $qb->where("a.site = :site");

        if($search->getDu() !== null)
        {
            $params[":du"] = $search->getDu()->format("Y-m-d");
            if($search->getAu() === null)
            {
                $qb->andWhere(":du BETWEEN a.dateDebut AND a.dateFin");
            }else
            {
                $qb->andWhere('((a.dateDebut BETWEEN :du AND :au) '
			. 'OR (a.dateFin BETWEEN :du AND :au) '
			. 'OR (:du BETWEEN a.dateDebut AND a.dateFin)'
			. 'OR (:au BETWEEN a.dateDebut AND a.dateFin))');

                $params[":au"] = $search->getAu()->format("Y-m-d");
            }
        }else
        {
            $qb->andWhere("a.dateDebut >= :now");
            $params[":now"] = new \DateTime;
        }


        if($search->getTerm() !== null and trim($search->getTerm()) !== "")
        {
            $qb->andWhere("(a.nom LIKE :mot_clefs OR a.descriptif LIKE :mot_clefs OR a.lieuNom LIKE :mot_clefs)");
            $params[":mot_clefs"] = "%".$search->getTerm()."%";
        }

        if($search->getTypeManifestation() !== null and count($search->getTypeManifestation()) > 0)
        {
            $qb->andWhere("a.typeManifestation IN(:type_manifesation)");
            $params[":type_manifesation"] = $search->getTypeManifestation();
        }

        if($search->getCommune() !== null and count($search->getCommune()) > 0)
        {
            $qb->andWhere("a.commune IN(:commune)");
            $params[":commune"] = $search->getCommune();
        }

        if($search->getTheme() !== null and count($search->getTheme()) > 0)
        {
            $qb->andWhere("a.themeManifestation IN(:theme_manifestation)");
            $params[":theme_manifestation"] = $search->getTheme();
        }

        return $qb
                ->setParameters($params);
    }

    public function findCountWithSearch(Site $site, SearchAgenda $search)
    {
        $qb = $this->_em
                ->createQueryBuilder()
                ->select('COUNT(a) as nombre')
                ->from('TBNAgendaBundle:Agenda','a');


        return
            $this->makeQuery($qb, $site, $search)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findWithSearch(Site $site, SearchAgenda $search, $page = 1, $limit = 15, $orderDesc = true)
    {
        $qb = $this->_em
                ->createQueryBuilder()
                ->select('a')
                ->from('TBNAgendaBundle:Agenda','a')
                ->orderBy('a.dateDebut', $orderDesc ? 'DESC' : 'ASC');
                //->addOrderBy('a.id', 'DESC');

        $soirees = $this->makeQuery($qb, $site, $search);

        if($page !== false and $limit !== false)
        {
            $soirees = $soirees
                ->setFirstResult(($page-1) * $limit)
                ->setMaxResults($limit);
        }

        return $soirees
                ->getQuery()
                ->execute();
    }

    public function getLieux(Site $site)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda',"a")
        ->where("a.site = :site")
        ->andWhere("a.lieuNom != ''")
        ->groupBy("a.lieuNom")
        ->setParameters([":site" => $site->getId()])
        ->getQuery()
        ->execute();
    }

    public function getFBOwners(Site $site)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda',"a")
        ->where("a.site = :site")
        ->andWhere("a.facebookOwnerId != ''")
        ->groupBy("a.facebookOwnerId")
        ->setParameters([":site" => $site->getId()])
        ->getQuery()
        ->execute();
    }

    public function getCommunes(Site $site)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda',"a")
        ->where("a.site = :site")
        ->andWhere("a.commune != ''")
	->andWhere("a.dateDebut >= :today")
        ->groupBy("a.commune")
        ->orderBy("a.commune", "DESC")
        ->setParameters([":site" => $site->getId(), "today" => new \DateTime])
        ->getQuery()
        ->execute();
    }

    public function getThemes(Site $site)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda',"a")
        ->where("a.site = :site")
        ->andWhere("a.themeManifestation != ''")
	->andWhere("a.dateDebut >= :today")
        ->groupBy("a.themeManifestation")
        ->orderBy("a.themeManifestation", "DESC")
        ->setParameters([":site" => $site->getId(), "today" => new \DateTime])
        ->getQuery()
        ->execute();
    }

    public function getTypesManifestation(Site $site)
    {
        return $this->_em
        ->createQueryBuilder()
        ->select('a')
        ->from('TBNAgendaBundle:Agenda',"a")
        ->where("a.site = :site")
        ->andWhere("a.typeManifestation != ''")
        ->andWhere("a.dateDebut >= :today")
        ->groupBy("a.typeManifestation")
        ->orderBy("a.typeManifestation", "DESC")
        ->setParameters([":site" => $site->getId(), "today" => new \DateTime])
        ->getQuery()
        ->execute();
    }
}