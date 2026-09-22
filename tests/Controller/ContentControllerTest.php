<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContentControllerTest extends WebTestCase
{
    public function testTheLegalNoticeNamesTheHostAndTheContact(): void
    {
        $client = self::createClient();

        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
        // The LCEN requires the host's name, address and phone number, and a way to reach the publisher
        self::assertAnySelectorTextContains('p', 'OVH SAS');
        self::assertAnySelectorTextContains('p', '2 rue Kellermann, 59100 Roubaix');
        self::assertSelectorExists('a[href="mailto:support@by-night.fr"]');
        self::assertSelectorExists('a[href="/cookie"]');
    }

    public function testFontsAreSelfHosted(): void
    {
        $client = self::createClient();

        $client->request('GET', '/cookie');

        self::assertResponseIsSuccessful();
        // fonts.googleapis.com would send every visitor's IP address to Google before any consent
        self::assertStringNotContainsString('fonts.googleapis.com', (string) $client->getResponse()->getContent());
    }

    public function testGoogleTagsWaitForTheConsentPlatform(): void
    {
        self::bootKernel();

        $html = self::getContainer()->get('twig')->render('fragments/_google-tags.html.twig');
        $consentDefault = strpos($html, "gtag('consent', 'default'");
        $gtmLoader = strpos($html, 'CONSENT_MODE_DATA_READY');
        $adsense = strpos($html, 'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');

        // Consent is denied, and Tag Manager queued behind the consent platform, before the AdSense tag
        // that brings the platform in
        self::assertNotFalse($consentDefault);
        self::assertNotFalse($gtmLoader);
        self::assertNotFalse($adsense);
        self::assertLessThan($adsense, $consentDefault);
        self::assertLessThan($adsense, $gtmLoader);
        self::assertStringNotContainsString('<script async src="https://www.googletagmanager.com/gtm.js', $html);
        // …and only loaded when the visitor granted at least one purpose
        self::assertStringContainsString('googlefc.getGoogleConsentModeValues()', $html);
    }

    public function testGoogleTagsOnlyRunInProduction(): void
    {
        $client = self::createClient();

        $client->request('GET', '/cookie');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        // Dev and test pages would otherwise count as AdSense traffic
        self::assertStringNotContainsString('adsbygoogle.js', $html);
        self::assertStringNotContainsString('googletagmanager.com', $html);
        self::assertStringNotContainsString('googlefc', $html);
        self::assertSelectorExists('footer a[data-cookie-consent]');
    }

    public function testTheCookiePolicyListsTheCookiesAndLetsTheVisitorChange(): void
    {
        $client = self::createClient();

        $client->request('GET', '/cookie');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de cookies');
        self::assertSelectorExists('main a.btn[data-cookie-consent], .card a.btn[data-cookie-consent]');
        foreach (['app_city', 'PHPSESSID', 'REMEMBERME', 'FCCDCF', '_ga'] as $cookie) {
            self::assertAnySelectorTextSame('code', $cookie);
        }
    }
}
