<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\MatchingInterface;
use App\Dto\CityDto;
use App\Entity\City;
use App\Utils\SluggerUtils;
use Override;
use WeakMap;

final class CityComparator extends AbstractComparator
{
    /** The postal code is the city's own */
    private const int SAME_POSTAL_CODE = 3;

    /** The postal code is of the city's département (area, outside France) */
    public const int SAME_AREA = 2;

    /** Nothing tells */
    private const int UNKNOWN = 1;

    /** The postal code is of another département */
    private const int ELSEWHERE = 0;

    /**
     * The compared form of each city's name: a lookup compares a name with every candidate
     * city, and sanitizing then slugging a name costs far more than comparing two strings.
     *
     * @var WeakMap<City, string>
     */
    private WeakMap $citySlugs;

    public function __construct(private readonly SluggerUtils $sluggerUtils)
    {
        $this->citySlugs = new WeakMap();
    }

    /**
     * Several cities of a country may bear the name (Pau is the prefecture of the
     * Pyrénées-Atlantiques and a hamlet of Savoie, Saint-Denis names dozens): the postal
     * code tells them apart, then the population. The first namesake the database returned
     * used to win, and 41% of the places on such cities sat in the wrong département.
     * A name borne by a single city is enough, as it always was.
     */
    #[Override]
    public function getMostMatching(iterable $entities, object $dto): ?MatchingInterface
    {
        \assert($dto instanceof CityDto);

        if (null === $dto->name) {
            return null;
        }

        $slug = $this->slug($dto->name);
        $namesakes = [];
        foreach ($entities as $entity) {
            \assert($entity instanceof City);
            if ($this->isNamesake($entity, $dto, $slug)) {
                $namesakes[] = $entity;
            }
        }

        if ([] === $namesakes) {
            return $this->getCityOfPostalCode($entities, $dto);
        }

        if (\count($namesakes) > 1) {
            $ranks = [];
            foreach ($namesakes as $city) {
                $ranks[spl_object_id($city)] = [$this->getPostalCodeAgreement($city, $dto->postalCode), $city->getPopulation() ?? 0, -($city->getId() ?? 0)];
            }

            usort($namesakes, static fn (City $a, City $b): int => $ranks[spl_object_id($b)] <=> $ranks[spl_object_id($a)]);
        }

        return new Matching($namesakes[0], 100.0);
    }

    /**
     * No city bears the name the source wrote ("PARIS 18EME", a commune nouvelle GeoNames
     * does not know): the postal code still locates the place when it is the code of a single
     * city. The lookup (CityRepository::findAllByDtos()) loads every city of the code.
     *
     * @param iterable<object> $entities
     */
    private function getCityOfPostalCode(iterable $entities, CityDto $dto): ?MatchingInterface
    {
        if (null === $dto->postalCode || '' === $dto->postalCode) {
            return null;
        }

        $cities = [];
        foreach ($entities as $entity) {
            \assert($entity instanceof City);
            if ($dto->country?->entityId === $entity->getCountry()?->getId()
                && self::SAME_POSTAL_CODE === $this->getPostalCodeAgreement($entity, $dto->postalCode)) {
                $cities[(int) $entity->getId()] = $entity;
            }
        }

        return 1 === \count($cities) ? new Matching(reset($cities), 100.0) : null;
    }

    /**
     * How much the postal code says the place is in this city, from 0 (in another
     * département) to 3 (it is the city's own).
     */
    public function getPostalCodeAgreement(City $city, ?string $postalCode): int
    {
        if (null === $postalCode || '' === $postalCode) {
            return self::UNKNOWN;
        }

        $area = mb_substr($postalCode, 0, 2);
        $sameArea = false;
        $zipCodes = 0;
        foreach ($city->getZipCities() as $zipCity) {
            ++$zipCodes;
            // "64001 CEDEX" is Pau's too
            $zipCode = strtok((string) $zipCity->getPostalCode(), ' ');
            if ($zipCode === $postalCode) {
                return self::SAME_POSTAL_CODE;
            }

            $sameArea = $sameArea || mb_substr((string) $zipCode, 0, 2) === $area;
        }

        if ($sameArea) {
            return self::SAME_AREA;
        }

        // A hamlet has no postal code of its own: its département still tells (GeoNames writes
        // "2" for the Aisne, "2A" and "2B" for Corsica, whose postal codes start with "20")
        $admin2Code = $city->getAdmin2Code();
        if ('FR' === $city->getCountry()?->getId() && null !== $admin2Code && ctype_digit($area)) {
            $department = '20' === $area ? ['2A', '2B'] : [ltrim($area, '0')];

            return \in_array($admin2Code, $department, true) ? self::SAME_AREA : self::ELSEWHERE;
        }

        return 0 === $zipCodes ? self::UNKNOWN : self::ELSEWHERE;
    }

    public function supports(object $object): bool
    {
        return $object instanceof CityDto;
    }

    public function getMatching(object $entity, object $dto): ?MatchingInterface
    {
        \assert($entity instanceof City);
        \assert($dto instanceof CityDto);

        if (null === $dto->name || !$this->isNamesake($entity, $dto, $this->slug($dto->name))) {
            return null;
        }

        return new Matching($entity, 100.0);
    }

    private function isNamesake(City $city, CityDto $dto, string $slug): bool
    {
        // We don't compare cities of different countries
        if ($dto->country?->entityId !== $city->getCountry()?->getId() || null === $city->getName()) {
            return false;
        }

        return ($this->citySlugs[$city] ??= $this->slug($city->getName())) === $slug;
    }

    private function slug(string $name): string
    {
        return $this->sluggerUtils->generateSlug($this->sanitize($name));
    }
}
