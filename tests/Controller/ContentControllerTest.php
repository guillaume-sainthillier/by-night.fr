<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Controller\ContentController;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContentControllerTest extends WebTestCase
{
    use CountsUpcomingEvents;

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

    /**
     * @param array<string, string> $countries
     */
    #[DataProvider('provideCountries')]
    public function testTheCountriesCaptionLeadsWithFranceAndGroupsTheOverseasTerritories(array $countries, string $expected): void
    {
        self::assertSame($expected, ContentController::summarizeCountries($countries));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideCountries(): iterable
    {
        yield 'France first, the others in their order, the territories counted' => [
            ['CH' => 'Suisse', 'RE' => 'La Réunion', 'FR' => 'France', 'MQ' => 'Martinique', 'BE' => 'Belgique'],
            "France, Suisse, Belgique et 2 territoires d'Outre-Mer",
        ];
        yield 'a single territory keeps its name' => [['FR' => 'France', 'YT' => 'Mayotte'], 'France et Mayotte'];
        yield 'no territory' => [['FR' => 'France'], 'France'];
        yield 'no France' => [['BE' => 'Belgique', 'GP' => 'Guadeloupe', 'GF' => 'Guyane'], "Belgique et 2 territoires d'Outre-Mer"];
        yield 'no event to come anywhere' => [[], ''];
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
