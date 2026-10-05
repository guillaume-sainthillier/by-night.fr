<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Dto\CountryDto;
use App\Entity\Country;
use App\Repository\CountryRepository;

/**
 * The {@see Country} row an imported {@see CountryDto} names, before entity resolution.
 *
 * Feeds carry an ISO code ("CH" from OpenAgenda or Awin) or a display name ("Suisse" from
 * DataTourisme); the personal-space form sends an already resolved id. Null when the DTO names
 * no country we serve (abroad, or a value no row matches).
 */
final readonly class CountryResolver
{
    public function __construct(private CountryRepository $countryRepository)
    {
    }

    public function resolve(?CountryDto $dto): ?Country
    {
        if (null === $dto) {
            return null;
        }

        // Already resolved (personal-space form): cheap identity-map hit.
        if (null !== $dto->entityId) {
            return $this->countryRepository->find($dto->entityId);
        }

        // Raw feed value: match by ISO code, name or display name (result-cached query).
        return $this->countryRepository->findAllByDtos([$dto], false)[0] ?? null;
    }
}
