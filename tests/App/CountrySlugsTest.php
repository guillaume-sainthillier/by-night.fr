<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\App;

use App\App\CountrySlugs;
use App\Factory\CountryFactory;
use App\Tests\AppKernelTestCase;

final class CountrySlugsTest extends AppKernelTestCase
{
    public function testTheCountrySlugsHaveNoPrefix(): void
    {
        CountryFactory::france()->create();

        self::assertTrue($this->countrySlugs()->has('france'));
        self::assertFalse($this->countrySlugs()->has('toulouse'));
    }

    public function testTheSlugsAreReadAgainForTheNextRequest(): void
    {
        CountryFactory::france()->create();
        self::assertFalse($this->countrySlugs()->has('belgique'));

        CountryFactory::belgium()->create();
        // What the worker does between two requests (kernel.reset)
        $this->countrySlugs()->reset();

        self::assertTrue($this->countrySlugs()->has('belgique'));
    }

    private function countrySlugs(): CountrySlugs
    {
        return self::getContainer()->get(CountrySlugs::class);
    }
}
