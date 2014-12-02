<?php
namespace TBN\AgendaBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use TBN\AgendaBundle\Search\SearchAgenda;
use TBN\MainBundle\Entity\Site;
use Elastica\Query\Bool as QueryBool;
use Elastica\Query\Term as QueryTerm;
use Elastica\Filter\Term as FilterTerm;
use Elastica\Filter\Bool as FilterBool;
use Elastica\Aggregation\Filter;
use Elastica\Query\MultiMatch;
use Elastica\Query\QueryString;
use Elastica\Filter\NumericRange;
use Elastica\Aggregation\Terms as AggTerms;


class AgendaRepository extends Repository {


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
            $params[":now"] = (new \DateTime)->format("Y-m-d");
        }


        if(count($search->getTerms()) > 0)
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

        if($search->getLieux() !== null and count($search->getLieux()) > 0)
        {
            $qb->andWhere("a.lieuNom IN(:lieux)");
            $params[":lieux"] = $search->getLieux();
        }

        return $qb
                ->setParameters($params);
    }

    /**
     * @param $searchText
     * @return array<Article>
     */
    public function findWithSearch(Site $site, SearchAgenda $search)
    {

        //$search->setDu(null);
        //$search->setTerm("Dynamo");

        $filters = new FilterBool();
        $queries = null;

//        $query->addMust(
//            new QueryTerm(['site.id' => ['value' => $site->getId()]])
//        );

        if($search->getDu() !== null)
        {
            if($search->getAu() === null)
            {
                // a < 5 AND b > 5 -> 5 entre [a; b]
                $filters->addMust(
                    new NumericRange('date_debut', [
                        'lte' => $search->getDu()->format("Y-m-d")
                    ])
                );
                $filters->addMust(
                    new NumericRange('date_fin', [
                        'gte' => $search->getDu()->format("Y-m-d"),
                    ])
                );

            }else
            {
                $qb->andWhere('((a.dateDebut BETWEEN :du AND :au) '
			. 'OR (a.dateFin BETWEEN :du AND :au) '
			. 'OR (:du BETWEEN a.dateDebut AND a.dateFin)'
			. 'OR (:au BETWEEN a.dateDebut AND a.dateFin))');

                $params[":au"] = $search->getAu()->format("Y-m-d");
            }
        }

        if($search->getTerm())
        {
            $filters = new FilterBool();
            $queryMatch  = new MultiMatch;
            $queryMatch->setQuery($search->getTerm())->setFields(["nom^3", "descriptif", "place.nom"]);

            $queries = new QueryBool;
            $queries->addShould(
                $queryMatch
            );
            
//            $query->addShould(
//                new QueryTerm(['descriptif' => ['value' => $search->getTerm()]])
//            );
//            $query->addShould(
//                new QueryTerm(['place.nom' => ['value' => $search->getTerm()]])
//            );
        }

        $query = new \Elastica\Query\Filtered($queries, $filters);
    

        // return $this->findHybrid($query); if you also want the ES ResultSet
        return $this->find($query);
    }
}