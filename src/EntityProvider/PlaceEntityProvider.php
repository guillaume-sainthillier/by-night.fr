<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\Handler\ComparatorHandler;
use App\Repository\PlaceRepository;
use App\Utils\PlaceNameNormalizer;
use Override;
use Psr\Log\LoggerInterface;

/**
 * @extends AbstractEntityProvider<PlaceDto, Place>
 */
final class PlaceEntityProvider extends AbstractEntityProvider
{
    /**
     * Cap for the city-wide fuzzy fallback to bound worst-case latency on large cities.
     */
    private const int FALLBACK_CITY_LIMIT = PlaceRepository::DEFAULT_CITY_FALLBACK_LIMIT;

    public function __construct(
        private readonly PlaceRepository $placeRepository,
        private readonly ComparatorHandler $comparatorHandler,
        private readonly PlaceNameNormalizer $placeNameNormalizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $dtoClassName): bool
    {
        return PlaceDto::class === $dtoClassName;
    }

    /**
     * Resolve places in three escalating passes, cheapest first:
     *  1. external id (indexed)          — handled by the parent in non-eager mode
     *  2. normalized name slug (indexed) — the de-duplication fast path
     *  3. bounded city-wide fuzzy scan   — last resort, capped
     *
     * {@inheritDoc}
     *
     * @param PlaceDto[] $dtos
     */
    #[Override]
    public function prefetchEntities(array $dtos, bool $eager = true): void
    {
        // Pass 1: external id (fast, indexed)
        if (!$eager) {
            parent::prefetchEntities($dtos, eager: false);

            return;
        }

        // Here $dtos are the ones left unmatched by external id.
        // Pass 2: normalized name slug (narrow, indexed)
        [$cityGroups, $countryGroups] = $this->buildSlugGroups($dtos);
        foreach ($this->placeRepository->findAllByNameSlugs($cityGroups, $countryGroups) as $entity) {
            $this->addEntity($entity);
        }

        // Pass 3: bounded city-wide fuzzy fallback for whatever is still unmatched
        $unmatched = array_values(array_filter($dtos, fn (PlaceDto $dto): bool => null === $this->getEntity($dto)));
        if ([] === $unmatched) {
            return;
        }

        [$cityIds, $countryIds] = $this->extractLocationIds($unmatched);
        if ([] === $cityIds && [] === $countryIds) {
            return;
        }

        $candidates = $this->placeRepository->findAllByCityBounded($cityIds, $countryIds, self::FALLBACK_CITY_LIMIT);
        if (\count($candidates) >= self::FALLBACK_CITY_LIMIT) {
            // No silent truncation: a genuinely new place in a huge city may be missed
            // and created as a duplicate; it self-heals once its slug is stored.
            $this->logger->warning('Place fuzzy fallback truncated at {limit} candidates; a duplicate may be created.', [
                'limit' => self::FALLBACK_CITY_LIMIT,
                'cities' => $cityIds,
                'countries' => $countryIds,
            ]);
        }

        foreach ($candidates as $entity) {
            $this->addEntity($entity);
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getEntity(object $dto): ?object
    {
        $entity = parent::getEntity($dto);
        if (null !== $entity) {
            return $entity;
        }

        $comparator = $this->comparatorHandler->getComparator($dto);
        $matching = $comparator->getMostMatching($this->getEntities(), $dto);

        if (null !== $matching && $matching->getConfidence() >= 90.0) {
            return $matching->getEntity();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface
    {
        return $this->placeRepository;
    }

    /**
     * @param PlaceDto[] $dtos
     *
     * @return array{0: array<int, list<string>>, 1: array<string, list<string>>} [cityId => slugs, countryCode => slugs]
     */
    private function buildSlugGroups(array $dtos): array
    {
        $cityGroups = [];
        $countryGroups = [];
        foreach ($dtos as $dto) {
            $slug = $this->placeNameNormalizer->normalize($dto->name, $dto->city?->name);
            if (null === $slug) {
                continue;
            }

            if (null !== $dto->city?->entityId) {
                $cityGroups[$dto->city->entityId][$slug] = $slug;
            } elseif (null !== $dto->country?->entityId) {
                $countryGroups[$dto->country->entityId][$slug] = $slug;
            }
        }

        return [
            array_map(static fn (array $slugs): array => array_values($slugs), $cityGroups),
            array_map(static fn (array $slugs): array => array_values($slugs), $countryGroups),
        ];
    }

    /**
     * @param PlaceDto[] $dtos
     *
     * @return array{0: list<int>, 1: list<string>} [cityIds, countryCodes]
     */
    private function extractLocationIds(array $dtos): array
    {
        $cityIds = [];
        $countryIds = [];
        foreach ($dtos as $dto) {
            if (null !== $dto->city?->entityId) {
                $cityIds[$dto->city->entityId] = true;
            } elseif (null !== $dto->country?->entityId) {
                $countryIds[$dto->country->entityId] = true;
            }
        }

        return [array_keys($cityIds), array_keys($countryIds)];
    }
}
