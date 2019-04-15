<?php

namespace App\SearchRepository;

use App\Search\SearchAgenda;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;
use Elastica\Query\MultiMatch;
use Elastica\Query\Range;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use FOS\ElasticaBundle\Repository;

class AgendaRepository extends Repository
{
    const EXPO_TERMS = 'exposition, salon';
    const CONCERT_TERMS = 'concert, musique, artiste';
    const FAMILY_TERMS = 'famille, enfants';
    const SHOW_TERMS = 'spectacle, exposition, théâtre, comédie';
    const STUDENT_TERMS = 'soirée, étudiant, bar, discothèque, boîte de nuit, after work';

    public function findWithSearch(SearchAgenda $search, bool $sortByScore = false)
    {
        $mainQuery = new BoolQuery();

        if ($search->getLieux()) {
            $mainQuery->addMust(
                new Terms('place.id', $search->getLieux())
            );
        } elseif ($search->getLocation() && $search->getLocation()->isCountry()) {
            $mainQuery->addMust(
                new Term(['place.country.id' => strtolower($search->getLocation()->getCountry()->getId())])
            );
        } elseif ($search->getLocation() && $search->getLocation()->isCity()) {
            $mainQuery->addMust(
                new Term(['place.city.id' => $search->getLocation()->getCity()->getId()])
            );
        }

        if ($search->getDu()) {
            if (!$search->getAu()) {
                $mainQuery->addMust(new Range('date_fin', [
                    'gte' => $search->getDu()->format('Y-m-d'),
                ]));
            } else {
                $filterDate = new BoolQuery();
                /*
                 * 4 cas :
                 * 1) [debForm; finForm] € [deb; fin]
                 * 2) [deb; fin] € [debForm; finForm]
                 * 3) deb € [debForm; finForm]
                 * 4) fin € [debForm; finForm]
                */

                //Cas1 : [debForm; finForm] € [deb; fin] -> (deb < debForm AND fin > finForm)
                $cas1 = new BoolQuery();
                $cas1->addMust(new Range('date_debut', [
                        'lte' => $search->getDu()->format('Y-m-d'),
                    ])
                )->addMust(new Range('date_fin', [
                    'gte' => $search->getAu()->format('Y-m-d'),
                ]));

                //Cas2 : [deb; fin] € [debForm; finForm] -> (deb > debForm AND fin < finForm)
                $cas2 = new BoolQuery();
                $cas2->addMust(new Range('date_debut', [
                        'gte' => $search->getDu()->format('Y-m-d'),
                    ])
                )->addMust(new Range('date_fin', [
                    'lte' => $search->getAu()->format('Y-m-d'),
                ]));

                //Cas3 : deb € [debForm; finForm] -> (deb > debForm AND deb < finForm)
                $cas3 = new Range('date_debut', [
                    'gte' => $search->getDu()->format('Y-m-d'),
                    'lte' => $search->getAu()->format('Y-m-d'),
                ]);

                //Cas4 : fin € [debForm; finForm] -> (fin > debForm AND fin < finForm)
                $cas4 = new Range('date_fin', [
                    'gte' => $search->getDu()->format('Y-m-d'),
                    'lte' => $search->getAu()->format('Y-m-d'),
                ]);

                $filterDate
                    ->addShould($cas1)
                    ->addShould($cas2)
                    ->addShould($cas3)
                    ->addShould($cas4);

                $mainQuery->addMust($filterDate);
            }
        }

        //Query
        if ($search->getTerm()) {
            $query = new MultiMatch();
            $query->setQuery($search->getTerm())
                ->setFields([
                    'nom', 'descriptif',
                    'type_manifestation', 'theme_manifestation', 'categorie_manifestation',
                    'place.nom', 'place.rue', 'place.ville', 'place.code_postal',
                    'place_name', 'place_street', 'place_city', 'place_postal_code',
                ])
                //->setAnalyzer('event_analyzer')
                //->setOperator(false === \strstr($search->getTerm(), ',') ? MultiMatch::OPERATOR_OR : MultiMatch::OPERATOR_AND)
                //->setOperator(MultiMatch::OPERATOR_AND)
                //->setFuzziness(0.8)
                //->setMinimumShouldMatch('80%');
            ;
            $mainQuery->addMust($query);
        }

        if ($search->getTag()) {
            $query = new MultiMatch();
            $query->setQuery($search->getTag())
                ->setFields(['type_manifestation', 'theme_manifestation', 'categorie_manifestation']);
            $mainQuery->addMust($query);
        }

        if ($search->getTypeManifestation()) {
            $communeTypeManifestationQuery = new Match();
            $communeTypeManifestationQuery->setField('type_manifestation', \implode(' ', $search->getTypeManifestation()));
            $mainQuery->addMust($communeTypeManifestationQuery);
        }

        //Construction de la requête finale
        $finalQuery = Query::create($mainQuery);

        if (false === $sortByScore) {
            $finalQuery
                ->addSort(['date_fin' => 'asc'])
                ->addSort(['fb_participations' => ['order' => 'desc', 'unmapped_type' => 'long']]);
        }

        return $this->createPaginatorAdapter($finalQuery);
    }
}
