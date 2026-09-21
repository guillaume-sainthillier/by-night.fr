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
 * Decides whether an imported postal code is plausible for the country it belongs to.
 *
 * Runs inside the {@see Firewall}, i.e. BEFORE entity resolution: the incoming
 * {@see CountryDto} usually carries only an ISO code ("CH" from OpenAgenda, Awin or the
 * personal-space form) or a display name ("Suisse" from DataTourisme or SowProg), so the
 * matching {@see Country} row is looked up here on purpose. The expected format lives in
 * {@see Country::getPostalCodeRegex()} (editable in the admin), so supporting a new
 * country is a data change, not a code change.
 */
final readonly class PostalCodeChecker
{
    public function __construct(private CountryRepository $countryRepository)
    {
    }

    public function accepts(?CountryDto $countryDto, ?string $postalCode): bool
    {
        // Feeds are messy ("F-31000", "31 000", "CH-1200"): only the digits are meaningful,
        // and Cleaner::cleanCity() strips the same characters before persistence.
        $digits = (string) preg_replace('#\D#', '', (string) $postalCode);

        return $this->matchesCountryFormat($this->resolveCountry($countryDto), $digits);
    }

    /**
     * Is $digits an acceptable postal code for $country?
     *
     * Strict policy: a code we cannot verify is rejected. That happens when the DTO
     * named a country we don't know ($country is null) or when the row has no pattern
     * configured yet (Country::$postalCodeRegex, editable in the admin). The event then
     * fails with BAD_PLACE_CITY_POSTAL_CODE instead of being stored with unverifiable
     * location data. Stored patterns are anchored and delimiter-less, e.g. `^[0-9]{5}$`
     * (see CountryFactory::france()).
     */
    private function matchesCountryFormat(?Country $country, string $digits): bool
    {
        // A missing code is not an error: city matching falls back to the name.
        if ('' === $digits) {
            return true;
        }

        $regex = $country?->getPostalCodeRegex();
        if (null === $regex || '' === $regex) {
            return false;
        }

        return 1 === preg_match('#' . $regex . '#', $digits);
    }

    private function resolveCountry(?CountryDto $dto): ?Country
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
