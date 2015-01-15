<?php

namespace TBN\AgendaBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use TBN\AgendaBundle\Search\SearchAgenda;
use TBN\MainBundle\Entity\Site;
use Elastica\Filter\Term;
use Elastica\Filter\Bool;
use Elastica\Query\Bool as QueryBool;
use Elastica\Query\QueryString;
use Elastica\Filter\NumericRange;
use Elastica\Query\Match;
use Elastica\Util;

class AgendaRepository extends Repository {

    /**
     * @param $searchText
     * @return array<Agenda>
     */
    public function findWithSearch(Site $site, SearchAgenda $search) {
        $filters = new Bool();
        $queries = new QueryBool();
        $analyzer = 'custom_french_analyzer';
        $filters->addMust(
            new Term(['site.id' => $site->getId()])
        );

        if ($search->getDu()) {
            if (!$search->getAu()) { //Pas de date de fin renseignée
                // 5 entre [a; b] -> a <= 5 AND b >= 5
                $filters->addMust(new NumericRange('date_debut', [
                    'lte' => $search->getDu()->format("Y-m-d")])
                )->addMust(new NumericRange('date_fin', [
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

                $filters->addMust($cas);
            }
        }

        if ($search->getTerm()) {
            $queries->addMust(
                (new QueryString(Util::replaceBooleanWordsAndEscapeTerm($search->getTerm())))
                //->setAnalyzer('custom_french_analyzer')
            );
        }

        if($search->getCommune())
        {
            $matchCommunes = new Match();
            $matchCommunes->setField('commune', Util::replaceBooleanWordsAndEscapeTerm(implode(" ", $search->getCommune())));
            $queries->addMust(
                $matchCommunes
                ->setFieldAnalyzer('commune', $analyzer)
            );
        }

        if($search->getTypeManifestation())
        {
            $matchTypeManifestation = new Match();
            $matchTypeManifestation->setField('type_manifestation', Util::replaceBooleanWordsAndEscapeTerm(implode(" ", $search->getTypeManifestation())));
            $queries->addMust(
                $matchTypeManifestation
                ->setFieldAnalyzer('type_manifestation', $analyzer)
            );
        }

        // return $this->findHybrid($query); if you also want the ES ResultSet
        return $this->find(new \Elastica\Query\Filtered(count($queries->getParams()) ? $queries : null, $filters));
    }

}
