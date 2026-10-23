<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;

final class ContentControllerTest extends AppWebTestCase
{
    use CountsUpcomingEvents;

    public function testTheLegalNoticeNamesTheHostAndTheContact(): void
    {
        $client = self::createClient();

        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
        // The LCEN requires the host's name, address and phone number, and a way to reach the publisher
        self::assertSelectorTextContains('#editeur', 'OVH SAS');
        self::assertSelectorTextContains('#editeur', '2 rue Kellermann, 59100 Roubaix');
        self::assertSelectorTextContains('#editeur', 'RCS Paris 942 070 392');
        self::assertSelectorTextContains('#editeur', 'Guillaume Sainthillier');
        self::assertSelectorExists('a[href="mailto:support@by-night.fr"]');
        self::assertSelectorExists('a[href="/cookie"]');
        self::assertSelectorExists('a[href="/confidentialite"]');
    }

    public function testThePrivacyPolicySaysWhatTheSocialLoginsShare(): void
    {
        $client = self::createClient();

        $client->request('GET', '/confidentialite');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
        self::assertSelectorExists('footer a[href="/confidentialite"]');
        // Google's brand verification wants the Google user data named, its use limited, and a way to revoke the access
        self::assertSelectorTextContains('#connexion-reseaux-sociaux', 'Google');
        self::assertSelectorExists('a[href="https://developers.google.com/terms/api-services-user-data-policy"]');
        self::assertSelectorExists('a[href="https://myaccount.google.com/connections"]');
        self::assertSelectorExists('a[href="mailto:support@by-night.fr"]');
    }

    public function testTheLegalPagesShareTheirTabs(): void
    {
        $client = self::createClient();

        foreach (['/mentions-legales', '/conditions-generales-d-utilisation', '/confidentialite', '/cookie', '/transparence'] as $url) {
            $client->request('GET', $url);

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(5, 'nav[aria-label="Documents juridiques"] a');
            self::assertSelectorExists(\sprintf('nav[aria-label="Documents juridiques"] a[aria-current="page"][href="%s"]', $url));
            // "Sur cette page" lists every section of the page
            self::assertSame(
                $client->getCrawler()->filter('.legal-section')->count(),
                $client->getCrawler()->filter('[data-toc] a')->count(),
            );
        }
    }

    public function testTheTermsCoverTheAccountTheLicenceAndTheNotices(): void
    {
        $client = self::createClient();

        $client->request('GET', '/conditions-generales-d-utilisation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "Conditions générales d'utilisation");
        foreach (['#compte', '#licence', '#moderation', '#signalement', '#sources'] as $section) {
            self::assertSelectorExists($section);
        }
        self::assertSelectorTextContains('#signalement', 'Signaler ce contenu');
        self::assertSelectorExists('#signalement a[href="mailto:support@by-night.fr"]');
        self::assertSelectorExists('footer a[href="/conditions-generales-d-utilisation"]');
    }

    public function testTheTransparencyPageSaysHowTheSiteEarnsMoney(): void
    {
        $client = self::createClient();

        $client->request('GET', '/transparence');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Transparence');
        self::assertSelectorTextContains('#financement', 'AdSense');
        self::assertSelectorTextContains('#financement', 'Awin');
        self::assertSelectorTextContains('#billets', "le lien de réservation de l'organisateur n'est pas affiché");
        self::assertSelectorExists('footer a[href="/transparence"]');
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
        foreach (['PHPSESSID', 'REMEMBERME', 'FCCDCF', '_ga'] as $cookie) {
            self::assertAnySelectorTextSame('code', $cookie);
        }
    }

    public function testTheAboutPageCountsTheCountriesWithEventsToCome(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $belgium = CountryFactory::createOne(['id' => 'BE', 'name' => 'Belgique', 'displayName' => 'Belgique']);
        $switzerland = CountryFactory::createOne(['id' => 'CH', 'name' => 'Suisse', 'displayName' => 'Suisse']);
        $tomorrow = new DateTimeImmutable('tomorrow');
        EventFactory::new()->withDates($tomorrow)->create(['place' => PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()])]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => PlaceFactory::createOne(['city' => CityFactory::createOne(['country' => $belgium]), 'country' => $belgium])]);
        // Only past events: not a country the agenda covers today
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['place' => PlaceFactory::createOne(['city' => CityFactory::createOne(['country' => $switzerland]), 'country' => $switzerland])]);
        self::counter()->refresh();

        $client->request('GET', '/a-propos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#stats .col-md-4:nth-child(3) .display-5', '2');
        self::assertSelectorTextContains('#stats .col-md-4:nth-child(3) p', 'France');
        self::assertSelectorTextContains('#stats .col-md-4:nth-child(3) p', 'Belgique');
        self::assertSelectorTextNotContains('#stats .col-md-4:nth-child(3) p', 'Suisse');
    }

    public function testTheHowItWorksPageCountsWhatTheMembersPublished(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();
        EventFactory::createMany(2, ['user' => $member]);
        $published = EventFactory::createOne();
        // Drafts, duplicates and imported events are not the members' publications
        EventFactory::createOne(['user' => $member, 'draft' => true]);
        EventFactory::createOne(['user' => $member, 'duplicateOf' => $published]);
        EventFactory::createOne(['user' => null]);

        $client->request('GET', '/en-savoir-plus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#member-totals', '3 événements publiés');
        self::assertSelectorTextContains('#member-totals', 'par 2 membres');
    }
}
