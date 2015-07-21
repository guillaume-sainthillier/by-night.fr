<?php

namespace TBN\AgendaBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use TBN\AgendaBundle\Search\SearchAgenda;
use TBN\MainBundle\Entity\Site;
use Elastica\Filter\Term;
use Elastica\Filter\Terms;
use Elastica\Filter\Bool;
use Elastica\Query;
use Elastica\Query\Bool as QueryBool;
use Elastica\Query\QueryString;
use Elastica\Filter\NumericRange;
use Elastica\Query\Match;
use Elastica\Util;
use Elastica\Query\MultiMatch;
use Elastica\Query\MatchAll;
use Elastica\Query\Filtered;

class AgendaRepository extends Repository {

    /**
     * @param $searchText
     * @return \Pagerfanta\Pagerfanta
     */
    public function findWithSearch(Site $site, SearchAgenda $search) {

	//Filters
        $filter = new Bool;
	$filter->addMust(
            new Term(['site.id' => $site->getId()])
        );        

        if ($search->getDu()) {
            if (!$search->getAu()) { 
                $filter->addMust(new NumericRange('date_fin', [
                    'gte' => $search->getDu()->format("Y-m-d")])
                );
            } else {
                /*
                 * 4 cas :
                 * - 1) [debForm; finForm] € [deb; fin]
                 * - 2) [deb; fin] € [debForm; finForm]
                 * - 3) deb € [debForm; finForm]
                 * - 4) fin € [debForm; finForm]                 *
                 */
                $cas = new Bool;

                //Cas1 : [debForm; finForm] € [deb; fin] -> (deb < debForm AND fin > finForm)
                $cas1 = new Bool;
                $cas1->addMust(new NumericRange('date_debut', [
                    'lte' => $search->getDu()->format("Y-m-d")
                        ])
                )->addMust(new NumericRange('date_fin', [
                    'gte' => $search->getAu()->format("Y-m-d"),
                ]));

                //Cas2 : [deb; fin] € [debForm; finForm] -> (deb > debForm AND fin < finForm)
                $cas2 = new Bool;
                $cas2->addMust(new NumericRange('date_debut', [
                    'gte' => $search->getDu()->format("Y-m-d")
                        ])
                )->addMust(new NumericRange('date_fin', [
                    'lte' => $search->getAu()->format("Y-m-d"),
                ]));

                //Cas3 : deb € [debForm; finForm] -> (deb > debForm AND deb < finForm)
                $cas3 = new Bool;
                $cas3->addMust(new NumericRange('date_debut', [
                    'gte' => $search->getDu()->format("Y-m-d"),
                    'lte' => $search->getAu()->format("Y-m-d")
                ]));

                //Cas4 : fin € [debForm; finForm] -> (fin > debForm AND fin < finForm)
                $cas4 = new Bool;
                $cas4->addMust(new NumericRange('date_fin', [
                    'gte' => $search->getDu()->format("Y-m-d"),
                    'lte' => $search->getAu()->format("Y-m-d")
                ]));

                $cas->addShould($cas1)
                        ->addShould($cas2)
                        ->addShould($cas3)
                        ->addShould($cas4);
		
                $filter->addMust($cas);
            }
        }

	//Query
	$queries = new QueryBool;
        if ($search->getTerm()) {
            $query = new MultiMatch;
	    $query->setQuery($search->getTerm())
		->setFields(['nom', 'descriptif', 'type_manifestation', 
		    'theme_manifestation', 'categorie_manifestation', 'place.nom', 'place.rue',
		    'place.ville.nom'])
		->setOperator('and')
		->setFuzziness(0.8)
		->setMinimumShouldMatch('80%')
		;
        }else
	{
	    $query = new MatchAll;
	}
	$queries->addMust($query);

        if($search->getLieux())
        {
	    $placeQuery = new Terms('place.id', $search->getLieux());
            $filter->addMust($placeQuery);
        }

        if($search->getCommune())
        {
	    $communeQuery = new Terms('place.ville.id', $search->getCommune());
            $filter->addMust($communeQuery);
        }

        if($search->getTypeManifestation())
        {
	    $communeTypeManifestationQuery = new Match;
	    $communeTypeManifestationQuery->setField('type_manifestation', implode(' ',$search->getTypeManifestation()));
            $queries->addMust($communeTypeManifestationQuery);
        }

        //Construction de la requête finale
	$filtered = new Filtered($queries, $filter);
	$finalQuery = Query::create($filtered)
                ->addSort(['date_fin' => 'asc', 'date_debut' => 'desc']);

        return $this->findPaginated($finalQuery);
    }
}
