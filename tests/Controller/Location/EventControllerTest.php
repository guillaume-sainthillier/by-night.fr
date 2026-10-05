<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Entity\Event;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use DateTimeImmutable;
use IntlDateFormatter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

use function Zenstruck\Foundry\Persistence\save;

final class EventControllerTest extends WebTestCase
{
    public function testAdminSeesEditButtonNextToTitle(): void
    {
        // createClient() must run before any factory call: factories boot the kernel,
        // and WebTestCase refuses to create a client on an already-booted kernel.
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf(
            '.event-header a[href="/_administration/event/%d/edit"]',
            $event->getId()
        ));
    }

    public function testRegularUserDoesNotSeeEditButton(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.event-header a[href^="/_administration/"]');
    }

    public function testAnonymousDoesNotSeeEditButton(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.event-header a[href^="/_administration/"]');
        self::assertSelectorTextContains('.event-header h1', $event->getName());
    }

    public function testTheAuthorSeesALinkToEditTheirEvent(): void
    {
        $client = self::createClient();
        $author = UserFactory::createOne();
        $event = $this->createEvent(author: $author);
        $client->loginUser($author);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('.event-header a[href="/espace-perso/%d"]', $event->getId()));
    }

    public function testAnotherMemberDoesNotSeeTheLinkToEditTheEvent(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(author: UserFactory::createOne());
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.event-header a[href^="/espace-perso/"]');
    }

    public function testJyVaisOpensALoginDialogThatLeadsBackToTheEventAndRecordsIt(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        // "#participer": the page clicks "J'y vais" for the member once they are back (like.js)
        $target = $this->eventUrl($event) . '#participer';
        // The login page without the script, the dialog with it
        self::assertSelectorExists(\sprintf('a.participate[href="%s"][data-bs-target="#login-dialog"]', $this->withTarget('/login', $target)));
        // Every way in leads back: the e-mail, a new account, a social network
        self::assertSelectorExists(\sprintf('#login-dialog a[href="%s"]', $this->withTarget('/login', $target)));
        self::assertSelectorExists(\sprintf('#login-dialog a[href="%s"]', $this->withTarget('/inscription', $target)));
        self::assertSelectorExists(\sprintf('#login-dialog a[href="%s"]', $this->withTarget('/login-social/google', $target)));
    }

    public function testTheLoginToCommentLeadsBackToTheComments(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();

        $client->request('GET', $this->eventUrl($event));

        self::assertSelectorExists(\sprintf('#comments a[href="%s"]', $this->withTarget('/login', $this->eventUrl($event) . '#comments')));
    }

    public function testTheLoginLinksThatLeadBackAreNotFollowed(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();

        $crawler = $client->request('GET', $this->eventUrl($event));

        // One login URL per event (its _target_path): crawlers would open a session on each of the millions of them
        $links = $crawler->filter('a[href*="_target_path="]')->each(static fn (Crawler $link): array => [$link->attr('href'), $link->attr('rel') ?? '']);
        self::assertNotEmpty($links);
        foreach ($links as [$href, $rel]) {
            self::assertContains('nofollow', explode(' ', $rel), (string) $href);
        }
    }

    public function testAMemberGetsNoLoginDialogButAButtonThatRecordsTheClickMadeBeforeTheLogin(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#login-dialog');
        self::assertSelectorExists('button.btn-like-event[data-intent="participer"]');
    }

    public function testTheCountryKeywordLeadsToTheCountryAgenda(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $country = $event->getPlace()?->getCountry();
        self::assertNotNull($country);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            \sprintf('.event-tags a[href="/%s"]', $country->getSlug()),
            'Événements ' . $country->getAtDisplayName(),
        );
    }

    public function testAnEventThatEndedLongAgoStaysIndexableWithAnEndedNotice(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('-10 years'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSelectorTextContains('#event-ended', 'Cet événement est terminé');
    }

    public function testADraftEventIsNotIndexed(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'), draft: true);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testAnonymousOnlyGetsANoticeOnADraft(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(draft: true);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', 'pas encore publié');
        self::assertSelectorNotExists('#event');
        self::assertSelectorNotExists('#event-draft-notice');
    }

    public function testAnotherMemberOnlyGetsANoticeOnADraft(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(draft: true);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#event');
        self::assertSelectorNotExists('#event-draft-notice');
    }

    public function testThePageSourceGivesNothingOfADraftAway(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(draft: true, poster: 'draft-poster.jpg');

        $crawler = $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Événement bientôt disponible');
        self::assertSelectorTextNotContains('title', (string) $event->getName());
        self::assertSelectorNotExists('meta[property="og:image"][content*="draft-poster.jpg"]');
        self::assertFalse($this->hasEventJsonLd($crawler));
        // Nor anywhere else a scraper reads: description, keywords, breadcrumb
        self::assertStringNotContainsString((string) $event->getName(), (string) $client->getResponse()->getContent());
    }

    public function testTryingTheIdOfADraftGivesNothingAway(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(draft: true);

        $client->request('GET', \sprintf('/toulouse/soiree/x--%d', $event->getId()));

        // Not a redirect to its URL: that URL holds its slug, and ids are sequential
        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('Location'));
        self::assertStringNotContainsString((string) $event->getSlug(), (string) $client->getResponse()->getContent());
    }

    public function testTheAuthorIsRedirectedFromAWrongUrlOfTheirDraft(): void
    {
        $client = self::createClient();
        $author = UserFactory::createOne();
        $event = $this->createEvent(draft: true, author: $author);
        $client->loginUser($author);

        $client->request('GET', \sprintf('/toulouse/soiree/x--%d', $event->getId()));

        self::assertResponseRedirects($this->eventUrl($event), 301);
    }

    public function testTheAuthorsPreviewKeepsTheEventInThePageSource(): void
    {
        $client = self::createClient();
        $author = UserFactory::createOne();
        $event = $this->createEvent(draft: true, author: $author, poster: 'draft-poster.jpg');
        $client->loginUser($author);

        $crawler = $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains((string) $event->getName());
        self::assertSelectorExists('meta[property="og:image"][content*="draft-poster.jpg"]');
        self::assertTrue($this->hasEventJsonLd($crawler));
    }

    public function testTheAuthorPreviewsTheirDraft(): void
    {
        $client = self::createClient();
        $author = UserFactory::createOne();
        $event = $this->createEvent(draft: true, author: $author);
        $client->loginUser($author);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#event');
        self::assertSelectorExists('#event-draft-notice');
        self::assertSelectorExists(\sprintf('#event-draft-notice button[data-publish-href="/api/events/%d/draft"]', $event->getId()));
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]', 'A preview stays out of the index');
    }

    public function testAnAdminPreviewsADraft(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(draft: true);
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#event');
        self::assertSelectorExists('#event-draft-notice');
    }

    public function testTheAuthorGetsNoDraftNoticeOncePublished(): void
    {
        $client = self::createClient();
        $author = UserFactory::createOne();
        $event = $this->createEvent(author: $author);
        $client->loginUser($author);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#event');
        self::assertSelectorNotExists('#event-draft-notice');
    }

    public function testAnUpcomingEventIsIndexable(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSelectorNotExists('#event-ended');
    }

    public function testTheTicketingOfAnUpcomingEventIsOneClickAway(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'));
        $event->setTicketUrl('https://billetterie.example.org/concert');
        save($event);

        $client->request('GET', $this->eventUrl($event));

        self::assertSelectorTextContains('a.btn[href="https://billetterie.example.org/concert"][rel="noopener nofollow"]', 'Réserver');
    }

    public function testAnEventThatIsOverOffersNoTicketing(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('-10 days'));
        $event->setTicketUrl('https://billetterie.example.org/concert');
        save($event);

        $client->request('GET', $this->eventUrl($event));

        self::assertSelectorNotExists('a.btn[href="https://billetterie.example.org/concert"]');
    }

    public function testThePageNamesTheArtists(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'));
        $event->setPerformers(['Seth', 'The Great Old Ones']);
        save($event);

        $client->request('GET', $this->eventUrl($event));

        self::assertSelectorTextContains('.event-facts', 'Artistes');
        self::assertSelectorTextContains('.event-facts', 'Seth, The Great Old Ones');
    }

    public function testALongListShowsTheNextSessionsAndFoldsThePastAndLaterOnes(): void
    {
        $client = self::createClient();
        $event = $this->createEventWithSessions([-3, -2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '.timesheets > .timesheet-entry');
        self::assertSelectorTextContains('.timesheets > .timesheet-entry', $this->day(1), 'The next session comes first');
        self::assertSelectorCount(2, '.timesheets > details');
        self::assertSelectorTextContains('.timesheets > details:first-child summary', 'Voir les 3 dates passées');
        self::assertSelectorCount(3, '.timesheets > details:first-child .timesheet-entry-past');
        self::assertSelectorTextContains('.timesheets > details:last-child summary', 'Voir les 4 dates suivantes');
        self::assertSelectorCount(12, '.timesheets .timesheet-entry', 'Every date stays in the page');
    }

    public function testAFoldOfASingleDateIsNotWorthALink(): void
    {
        $client = self::createClient();
        // One past session and six to come: five shown, one left over
        $event = $this->createEventWithSessions([-1, 1, 2, 3, 4, 5, 6]);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.timesheets details');
        self::assertSelectorCount(7, '.timesheets > .timesheet-entry');
        self::assertSelectorCount(1, '.timesheets > .timesheet-entry-past');
    }

    public function testAnEventThatIsOverListsItsSessionsFromTheStart(): void
    {
        $client = self::createClient();
        $event = $this->createEventWithSessions([-20, -19, -18, -17, -16, -15, -14, -13]);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '.timesheets > .timesheet-entry');
        self::assertSelectorTextContains('.timesheets > .timesheet-entry', $this->day(-20));
        self::assertSelectorCount(1, '.timesheets > details');
        self::assertSelectorTextContains('.timesheets > details summary', 'Voir les 3 dates suivantes');
    }

    public function testAVisitorsPageIsSharedByTheCdnButNotKeptByTheBrowser(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('-10 years'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        // Ended: a week (EventPageCache), served stale while Cloudflare asks again or the origin is down
        self::assertResponseHeaderSame('Cache-Control', 'max-age=0, public, s-maxage=604800, stale-if-error=86400, stale-while-revalidate=86400');
        self::assertResponseHeaderSame('Cache-Tag', \sprintf('event,event-%d,place-%d', $event->getId(), $event->getPlace()?->getId()));
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public function testAHiddenDraftsNoticeIsTaggedSoPublishingPurgesIt(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(draft: true);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseHeaderSame('Cache-Tag', \sprintf('event,event-%d,place-%d', $event->getId(), $event->getPlace()?->getId()));
    }

    public function testAMembersPageStaysPrivate(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('public'));
    }

    public function testAVisitorAboutToBeRememberedGetsAPrivatePage(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $client->getCookieJar()->set(new Cookie('REMEMBERME', 'not-a-valid-token'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    /**
     * @param list<int> $days sessions, in days from today
     */
    private function createEventWithSessions(array $days): Event
    {
        $city = CityFactory::toulouse()->create();

        return EventFactory::createOne([
            'name' => 'Exposition',
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            'startDate' => new DateTimeImmutable(\sprintf('%+d days', min($days))),
            'endDate' => new DateTimeImmutable(\sprintf('%+d days', max($days))),
            'timesheets' => array_map(
                static fn (int $day) => EventTimesheetFactory::new()->on(\sprintf('%+d days', $day)),
                $days,
            ),
        ]);
    }

    /**
     * A session's day as the page writes it, e.g. "samedi 26 septembre 2026".
     */
    private function day(int $offset): string
    {
        return new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE)->format(new DateTimeImmutable(\sprintf('%+d days', $offset)));
    }

    /**
     * Whether a JSON-LD block of the page describes the event: its type is a subtype as often as "Event", its status
     * is always there.
     */
    private function hasEventJsonLd(Crawler $crawler): bool
    {
        return \in_array(true, $crawler->filter('script[type="application/ld+json"]')->each(
            static fn (Crawler $script): bool => isset(json_decode($script->text(), true, flags: \JSON_THROW_ON_ERROR)['eventStatus']),
        ), true);
    }

    private function createEvent(?DateTimeImmutable $date = null, bool $draft = false, ?User $author = null, ?string $poster = null): Event
    {
        $city = CityFactory::toulouse()->create();
        $attributes = [
            'name' => 'Concert au Bikini',
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            'draft' => $draft,
        ];
        if (null !== $author) {
            $attributes['user'] = $author;
        }
        if (null !== $poster) {
            $image = new EmbeddedFile();
            $image->setName($poster);
            $attributes['image'] = $image;
        }
        if (null !== $date) {
            $attributes += ['startDate' => $date, 'endDate' => $date];
        }

        return EventFactory::createOne($attributes);
    }

    /**
     * A login URL leading back to $target, as the router writes it: the slashes of a query stay as they are.
     */
    private function withTarget(string $path, string $target): string
    {
        return $path . '?_target_path=' . str_replace('%2F', '/', rawurlencode($target));
    }

    private function eventUrl(Event $event): string
    {
        return \sprintf(
            '/%s/soiree/%s--%d',
            $event->getLocationSlug(),
            $event->getSlug(),
            $event->getId()
        );
    }
}
