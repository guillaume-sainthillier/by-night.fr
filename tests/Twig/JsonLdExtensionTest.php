<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Tests\AppKernelTestCase;
use App\Twig\JsonLdExtension;
use Huluti\BreadcrumbsBundle\Model\Breadcrumbs;

final class JsonLdExtensionTest extends AppKernelTestCase
{
    public function testAClosingScriptTagInAValueCannotLeaveTheBlock(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addItem('Recherche', 'https://by-night.fr/recherche/');
        $breadcrumbs->addItem('</script><img src=x onerror=alert(1)> & co', 'https://by-night.fr/recherche/?q=x');

        $html = $this->getExtension()->breadcrumbJsonLd($breadcrumbs);

        self::assertStringStartsWith('<script type="application/ld+json">', $html);
        self::assertSame(1, substr_count(strtolower($html), '</script'));
        self::assertStringNotContainsString('<img', $html);

        $json = substr($html, \strlen('<script type="application/ld+json">'), -\strlen('</script>'));
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('</script><img src=x onerror=alert(1)> & co', $data['itemListElement'][1]['name']);
    }

    public function testNoBreadcrumbRendersNothing(): void
    {
        self::assertSame('', $this->getExtension()->breadcrumbJsonLd(new Breadcrumbs()));
    }

    private function getExtension(): JsonLdExtension
    {
        return self::getContainer()->get(JsonLdExtension::class);
    }
}
