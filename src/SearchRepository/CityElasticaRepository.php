<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use Elastica\Query;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;

final class CityElasticaRepository extends Repository
{
    public function findWithSearch(?string $q): PagerfantaInterface
    {
        $query = new MultiMatch();
        $query
            ->setFields([
                'postalCodes^10',
                'postalCodes.autocomplete^8',
                'country.name^5',
                'name^5',
                'name.autocomplete^3',
                'parent.name',
            ])
            ->setFuzziness(Fuzzy::FUZZINESS)
            ->setPrefixLength(Fuzzy::PREFIX_LENGTH)
            ->setOperator('AND')
            ->setQuery($q ?? '')
        ;

        $finalQuery = Query::create($query);
        // The hits are loaded from the database by their _id (FOSElastica's transformer): none of the document is read
        $finalQuery->setSource(false);
        $finalQuery->addSort(['_score' => 'DESC']);
        $finalQuery->addSort(['population' => 'DESC']);

        return $this->findPaginated($finalQuery);
    }

    /**
     * Returns a paginated list of hybrid results with highlights.
     *
     * @return PagerfantaInterface<HybridResult>
     */
    public function findWithHighlightsPaginated(string $query): PagerfantaInterface
    {
        $multiMatch = new MultiMatch();
        $multiMatch
            ->setFields([
                'postalCodes^10',
                'postalCodes.autocomplete^8',
                'country.name^5',
                'name^5',
                'name.autocomplete^3',
                'parent.name',
            ])
            ->setFuzziness(Fuzzy::FUZZINESS)
            ->setPrefixLength(Fuzzy::PREFIX_LENGTH)
            ->setOperator('AND')
            ->setQuery($query);

        $finalQuery = Query::create($multiMatch);
        // Loaded from the database by their _id; the highlights come without the document
        $finalQuery->setSource(false);
        $finalQuery->addSort(['_score' => 'DESC']);
        $finalQuery->addSort(['population' => 'DESC']);

        // Add highlighting
        $finalQuery->setHighlight([
            'fields' => [
                'name' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
                'country.name' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
            ],
        ]);

        $adapter = $this->createHybridPaginatorAdapter($finalQuery);

        return new Pagerfanta(new FantaPaginatorAdapter($adapter));
    }
}
