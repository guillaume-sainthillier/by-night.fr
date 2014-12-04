<?php

namespace TBN\AgendaBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use TBN\AgendaBundle\Search\SearchAgenda;
use TBN\MainBundle\Entity\Site;
use Elastica\Filter\Term;
use Elastica\Filter\Terms;
use Elastica\Filter\Bool;
use Elastica\Query\QueryString;
use Elastica\Filter\NumericRange;

class AgendaRepository extends Repository {

    protected function makeQuery(QueryBuilder $qb, Site $site, SearchAgenda $search) {
        $params = [":site" => $site->getId()];
        $qb->where("a.site = :site");

        if ($search->getDu() !== null) {
            $params[":du"] = $search->getDu()->format("Y-m-d");
            if ($search->getAu() === null) {
                $qb->andWhere(":du BETWEEN a.date_debut AND a.date_fin");
            } else {
                $qb->andWhere('((a.date_debut BETWEEN :du AND :au) '
                        . 'OR (a.date_fin BETWEEN :du AND :au) '
                        . 'OR (:du BETWEEN a.date_debut AND a.date_fin)'
                        . 'OR (:au BETWEEN a.date_debut AND a.date_fin))');

                $params[":au"] = $search->getAu()->format("Y-m-d");
            }
        } else {
            $qb->andWhere("a.date_debut >= :now");
            $params[":now"] = (new \DateTime)->format("Y-m-d");
        }


        if (count($search->getTerms()) > 0) {
            $qb->andWhere("(a.nom LIKE :mot_clefs OR a.descriptif LIKE :mot_clefs OR a.lieuNom LIKE :mot_clefs)");
            $params[":mot_clefs"] = "%" . $search->getTerm() . "%";
        }

        if ($search->getTypeManifestation() !== null and count($search->getTypeManifestation()) > 0) {
            $qb->andWhere("a.typeManifestation IN(:type_manifesation)");
            $params[":type_manifesation"] = $search->getTypeManifestation();
        }
        if ($search->getCommune() !== null and count($search->getCommune()) > 0) {
            $qb->andWhere("a.commune IN(:commune)");
            $params[":commune"] = $search->getCommune();
        }

        if ($search->getLieux() !== null and count($search->getLieux()) > 0) {
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
    public function findWithSearch(Site $site, SearchAgenda $search) {
        $search->setDu(null);
        //$search->setDu(new \DateTime("2010-01-01"));
        //$search->setAu(new \DateTime("2020-01-01"));
        //$search->setTerm("Toulouse");
        $search->setTypeManifestation(["Musique"]);
        $filters = new Bool();
        $query = null;

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

                //Cas1 : [debForm; finForm] € [deb; fin] -> (deb > debForm AND fin < finForm)
                $cas1 = new Bool;
                $cas1->addMust(new NumericRange('date_debut', [
                    'gte' => $search->getDu()->format("Y-m-d")
                        ])
                )->addMust(new NumericRange('date_fin', [
                    'lte' => $search->getAu()->format("Y-m-d"),
                ]));

                //Cas2 : [deb; fin] € [debForm; finForm] -> (deb < debForm AND fin < finForm)
                $cas2 = new Bool;
                $cas2->addMust(new NumericRange('date_debut', [
                    'lte' => $search->getDu()->format("Y-m-d")
                        ])
                )->addMust(new NumericRange('date_fin', [
                    'lte' => $search->getAu()->format("Y-m-d"),
                ]));

                //Cas3 : deb € [debForm; finForm] -> (deb < debForm AND deb > finForm)
                $cas3 = new Bool;
                $cas3->addMust(new NumericRange('date_debut', [
                    'lte' => $search->getDu()->format("Y-m-d"),
                    'gte' => $search->getAu()->format("Y-m-d")
                ]));

                //Cas4 : fin € [debForm; finForm] -> (fin < debForm AND fin > finForm)
                $cas4 = new Bool;
                $cas4->addMust(new NumericRange('date_fin', [
                    'lte' => $search->getDu()->format("Y-m-d"),
                    'gte' => $search->getAu()->format("Y-m-d")
                ]));

                $cas->addShould($cas1)
                        ->addShould($cas2)
                        ->addShould($cas3)
                        ->addShould($cas4);

                $filters->addMust($cas);
            }
        }

        if ($search->getTerm()) {
            $query = new QueryString($search->getTerm());
        }

        if($search->getCommune())
        {
            var_dump("OK", $search->getCommune());
            //$filters->addMust(new Terms('commune', $search->getCommune()));
        }

        if($search->getTypeManifestation())
        {
            var_dump("VOK", $search->getTypeManifestation());
            //$filters->addMust(new Terms('type_manifestation', $search->getTypeManifestation()));
            //$filters->addMust(new Term(['typeManifestation' => 'Musique']));
            $filters->addMust(
                new Term(['type_manifestation' => 'Musique'])
        );
        }

        // return $this->findHybrid($query); if you also want the ES ResultSet
        return $this->find(new \Elastica\Query\Filtered($query, $filters));
    }

}
