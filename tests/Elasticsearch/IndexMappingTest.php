<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use App\Enum\AgendaType;
use App\Enum\PricePreset;
use App\Search\DateRange;
use App\Search\SearchEvent;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\TagElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Elastica\Query;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Index\MappingBuilder;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use Pagerfanta\Adapter\NullAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The indexes map only the fields config/packages/fos_elastica.yaml declares (dynamic: false): a field the documents
 * carry but the mapping leaves out is kept in _source and never indexed. Every field a query, a sort, an aggregation
 * or a highlight of the search repositories reads must then be declared, or it would silently match nothing.
 */
final class IndexMappingTest extends AppKernelTestCase
{
    /** The options of a geo_distance query or sort, next to its field */
    private const array GEO_DISTANCE_OPTIONS = ['distance', 'distance_type', 'validation_method', 'order', 'unit', 'mode', 'ignore_unmapped', 'boost', '_name'];

    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /** @var array<string, bool> the fields read by the queries, and whether each is a nested path */
    private array $fields = [];

    #[DataProvider('provideIndexes')]
    public function testOnlyTheDeclaredFieldsAreIndexed(string $index): void
    {
        self::assertFalse($this->mappingOf($index)['dynamic']);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideIndexes(): iterable
    {
        yield ['event'];
        yield ['city'];
        yield ['user'];
        yield ['tag'];
    }

    #[DataProvider('provideQueries')]
    public function testEveryFieldTheSearchesReadIsDeclared(string $index, string $search): void
    {
        $mapping = $this->mappingOf($index);
        $fields = $this->fieldsOf($this->queriesOf($search));
        self::assertNotEmpty($fields);

        foreach ($fields as $field => $nested) {
            $declared = $this->declaration($mapping, $field);
            self::assertNotNull($declared, \sprintf('"%s" (%s) is not mapped in the %s index', $field, $search, $index));
            if ($nested) {
                self::assertSame('nested', $declared['type'] ?? null, \sprintf('"%s" is queried as a nested path', $field));
            }
        }
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideQueries(): iterable
    {
        yield ['event', 'agenda'];
        yield ['event', 'facets'];
        yield ['event', 'agenda types'];
        yield ['event', 'event autocomplete'];
        yield ['city', 'city'];
        yield ['user', 'user'];
        yield ['tag', 'tag'];
    }

    /**
     * The country filter asks for the code as stored ("FR"): a keyword, not the text the dynamic mapping made of it.
     */
    public function testTheCountryOfAnEventIsAKeyword(): void
    {
        $event = $this->mappingOf('event');

        self::assertSame(['type' => 'keyword'], $this->declaration($event, 'place.country.id'));
        self::assertArrayNotHasKey('country', $event['properties'], 'An event document has no country of its own');
    }

    /**
     * Only the keywords multi_match reads them: no phrase query nor highlight needs their positions.
     */
    public function testTheFieldsNoPhraseReadsHaveNoPositions(): void
    {
        $event = $this->mappingOf('event');

        foreach (['description', 'description.heavy', 'placeStreet', 'placeCity', 'placePostalCode', 'place.street', 'place.cityName', 'place.cityPostalCode'] as $field) {
            self::assertSame('freqs', $this->declaration($event, $field)['index_options'] ?? null, $field);
        }

        // The phrases of the agenda types (createAnyTermQuery()) and the highlights of the autocomplete
        foreach (['name', 'name.heavy', 'type', 'category.name', 'themes.name', 'place.name', 'placeName'] as $field) {
            self::assertArrayNotHasKey('index_options', $this->declaration($event, $field) ?? [], $field);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingOf(string $index): array
    {
        $configManager = self::getContainer()->get('fos_elastica.config_manager');
        self::assertInstanceOf(ConfigManager::class, $configManager);
        $mappingBuilder = self::getContainer()->get('fos_elastica.mapping_builder');
        self::assertInstanceOf(MappingBuilder::class, $mappingBuilder);

        return $mappingBuilder->buildIndexMapping($configManager->getIndexConfiguration($index))['mappings'];
    }

    /**
     * The mapping of a field ("place.city.location"), a sub-field ("name.heavy") included.
     *
     * @param array<string, mixed> $mapping
     *
     * @return array<string, mixed>|null
     */
    private function declaration(array $mapping, string $field): ?array
    {
        $node = $mapping;
        foreach (explode('.', $field) as $part) {
            $node = $node['properties'][$part] ?? $node['fields'][$part] ?? null;
            if (null === $node) {
                return null;
            }
        }

        return $node;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queriesOf(string $search): array
    {
        $this->captured = [];
        $from = new DateTimeImmutable('2026-10-10');

        $events = new EventElasticaRepository($this->finder());
        switch ($search) {
            case 'agenda':
                $france = new Location()->setCountry(new Country()->setId('FR'));
                $toulouse = new Location()->setCity(new City()->setId(2972315));

                return [
                    $events->createSearchQuery(new SearchEvent()->setFrom($from)->setLocation($france)->setTerm('jazz'))->toArray(),
                    $events->createSearchQuery(new SearchEvent()->setFrom($from)->setTo($from)->setLocation($toulouse)->setType(AgendaType::Concert)->setTagId(7)->setPrice(PricePreset::Under20))->toArray(),
                    $events->createSearchQuery(new SearchEvent()->setFrom($from)->setLieux([12]))->toArray(),
                ];
            case 'facets':
                $search = new SearchEvent()->setFrom($from)->setLocation(new Location()->setCountry(new Country()->setId('FR')))->setType(AgendaType::Show)->setTagId(7)->setTerm('jazz')->setPrice(PricePreset::Free);

                return [$events->createFacetsQuery($search, ['anytime' => new DateRange($from)], 6, 4)->toArray()];
            case 'agenda types':
                return [$events->createAgendaTypesQuery($from)->toArray()];
            case 'event autocomplete':
                $events->findWithHighlightsPaginated('jazz');

                return $this->captured;
            case 'city':
                $cities = new CityElasticaRepository($this->finder());
                $cities->findWithSearch('toulouse');
                $cities->findWithHighlightsPaginated('toulouse');

                return $this->captured;
            case 'user':
                $users = new UserElasticaRepository($this->finder());
                $users->findWithSearch('jazzfan');
                $users->findWithHighlightsPaginated('jazzfan');

                return $this->captured;
            case 'tag':
                $tags = new TagElasticaRepository($this->finder());
                $tags->findWithSearch('concert');
                $tags->findWithHighlightsPaginated('concert');

                return $this->captured;
        }

        self::fail(\sprintf('Unknown search "%s"', $search));
    }

    private function finder(): PaginatedFinderInterface
    {
        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('findPaginated')->willReturnCallback(function (Query $query): Pagerfanta {
            $this->captured[] = $query->toArray();

            return new Pagerfanta(new NullAdapter());
        });
        $capture = function (Query $query): PaginatorAdapterInterface {
            $this->captured[] = $query->toArray();

            return $this->createStub(PaginatorAdapterInterface::class);
        };
        $finder->method('createPaginatorAdapter')->willReturnCallback($capture);
        $finder->method('createHybridPaginatorAdapter')->willReturnCallback($capture);

        return $finder;
    }

    /**
     * The fields the queries read, each with whether it is the path of a nested query or sort.
     *
     * @param list<array<string, mixed>> $queries
     *
     * @return array<string, bool>
     */
    private function fieldsOf(array $queries): array
    {
        $this->fields = [];
        foreach ($queries as $query) {
            $this->collect($query);
        }

        return $this->fields;
    }

    private function collect(mixed $node): void
    {
        if (!\is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            switch ($key) {
                case 'term':
                case 'terms':
                case 'range':
                case 'match':
                case 'match_phrase':
                    $this->addFields($value, ['boost', '_name']);
                    break;
                case 'geo_distance':
                case '_geo_distance':
                    $this->addFields($value, self::GEO_DISTANCE_OPTIONS);
                    break;
                case 'multi_match':
                    foreach ($value['fields'] as $field) {
                        $this->addField(explode('^', $field)[0]);
                    }
                    break;
                case 'nested':
                    $this->addField($value['path'], true);
                    $this->collect($value);
                    break;
                case 'sort':
                    foreach ($value as $sort) {
                        // "_doc", "_score", "_geo_distance" (its field is collected below)
                        $this->addFields(\is_array($sort) ? $sort : [], ['_doc', '_score', '_geo_distance']);
                        $this->collect($sort);
                    }
                    break;
                case 'highlight':
                    $this->addFields($value['fields']);
                    break;
                case 'aggs':
                    foreach ($value as $aggregation) {
                        if (isset($aggregation['terms']['field'])) {
                            $this->addField($aggregation['terms']['field']);
                            unset($aggregation['terms']);
                        }

                        $this->collect($aggregation);
                    }
                    break;
                default:
                    $this->collect($value);
            }
        }
    }

    /**
     * @param array<mixed> $clause  the fields as keys, next to the options
     * @param list<string> $options the keys that are options, not fields
     */
    private function addFields(array $clause, array $options = []): void
    {
        foreach ($clause as $field => $value) {
            if (\is_string($field) && !\in_array($field, $options, true)) {
                $this->addField($field);
            }
        }
    }

    private function addField(string $field, bool $nested = false): void
    {
        $this->fields[$field] = $nested || ($this->fields[$field] ?? false);
    }
}
