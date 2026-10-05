<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Repository\CountryRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The slugs of the countries. Countries and cities share the first segment of the URLs ("/france", "/toulouse"): a
 * slug names a country first, and no city takes a country's slug (CitySlugHandler). Read once per request (a query of
 * a few rows) and never kept longer: a cache could outlive a country saved by SQL, the migration of a deploy.
 */
final class CountrySlugs implements ResetInterface
{
    /** @var array<string, true>|null */
    private ?array $slugs = null;

    public function __construct(private readonly CountryRepository $countryRepository)
    {
    }

    public function has(string $slug): bool
    {
        $this->slugs ??= array_fill_keys($this->countryRepository->findSlugs(), true);

        return isset($this->slugs[$slug]);
    }

    /**
     * The worker serves the next request with the same services: they read the slugs again.
     */
    public function reset(): void
    {
        $this->slugs = null;
    }
}
