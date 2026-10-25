<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Publisher;

use App\Entity\Event;
use App\Entity\Publisher;
use App\Enum\PublisherDistinction;
use App\Factory\EventFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\save;

final class PublisherControllerTest extends AppWebTestCase
{
    public function testThePageListsThePublishersEventsOnline(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        $upcoming = $this->createEvent($publisher, '+10 days', ['name' => 'Concert de jazz']);
        $this->createEvent($publisher, '-10 days', ['name' => 'Concert de rock']);
        $this->createEvent($publisher, '+10 days', ['name' => 'Brouillon', 'draft' => true]);
        $this->createEvent($publisher, '+10 days', ['name' => 'Doublon', 'duplicateOf' => $upcoming]);
        $this->createEvent(PublisherFactory::createOne(), '+10 days', ['name' => 'Concert ailleurs']);

        $crawler = $client->request('GET', '/organisateurs/le-bikini');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Le Bikini');
        self::assertSame(['Concert de jazz'], $crawler->filter('#a-venir .card-body h3')->each(static fn ($title): string => trim($title->text())));
        self::assertSame(['Concert de rock'], $crawler->filter('#passes .card-body h3')->each(static fn ($title): string => trim($title->text())));
        self::assertSelectorNotExists('meta[name="robots"]');
    }

    public function testThePageLeadsToTheMemberWhoRunsIt(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne(['username' => 'Gérant']);
        PublisherFactory::createOne(['name' => 'Le Bikini', 'owner' => $owner]);

        $client->request('GET', '/organisateurs/le-bikini');

        self::assertSelectorExists(\sprintf('a[href="/membres/%s--%d"]', $owner->getSlug(), $owner->getId()));
    }

    public function testThePageShowsWhatThePublisherSaysOfItself(): void
    {
        $client = self::createClient();
        PublisherFactory::createOne([
            'name' => 'Le Bikini',
            'description' => 'Salle de concerts à Ramonville.',
            'website' => 'https://lebikini.com',
            'socialLinks' => ['instagram' => 'https://www.instagram.com/lebikini'],
        ]);

        $client->request('GET', '/organisateurs/le-bikini');

        self::assertSelectorTextContains('.publisher-description', 'Salle de concerts à Ramonville.');
        self::assertSelectorExists('a[href="https://lebikini.com"]');
        self::assertSelectorExists('a[href="https://www.instagram.com/lebikini"][title="Le Bikini sur Instagram"]');
        self::assertSelectorExists('meta[name="description"][content="Salle de concerts à Ramonville."]');
    }

    public function testOnlyItsOwnerSeesWhatIsLeftToComplete(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        PublisherFactory::createOne(['name' => 'Le Bikini', 'owner' => $owner]);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/organisateurs/le-bikini');
        self::assertSelectorNotExists('aside a[href="/espace-perso/organisateurs/le-bikini/page"]');

        $client->loginUser($owner);
        $client->request('GET', '/organisateurs/le-bikini');
        self::assertSelectorTextContains('aside a[href="/espace-perso/organisateurs/le-bikini/page"]', 'Compléter');
    }

    public function testItsDistinctionsAreShown(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        for ($i = 1; $i <= PublisherDistinction::PROLIFIC_EVENTS; ++$i) {
            $this->createEvent($publisher, \sprintf('-%d days', $i), ['name' => 'Concert ' . $i]);
        }

        $client->request('GET', '/organisateurs/le-bikini');

        self::assertSelectorTextContains('aside', 'Prolifique');
    }

    public function testAPublisherWithoutAnyEventIsNotIndexable(): void
    {
        $client = self::createClient();
        PublisherFactory::createOne(['name' => 'Le Bikini']);

        $client->request('GET', '/organisateurs/le-bikini');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testADeletedPublisherIsGone(): void
    {
        $client = self::createClient();
        delete(PublisherFactory::createOne(['slug' => 'le-collectif']));

        $client->request('GET', '/organisateurs/le-collectif');

        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    public function testAnUnknownPublisherIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/organisateurs/inconnu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheNextPagesOfItsEventsLoadOnRequest(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        for ($i = 1; $i <= 7; ++$i) {
            $this->createEvent($publisher, \sprintf('+%d days', $i), ['name' => 'Concert ' . $i]);
        }

        $crawler = $client->request('GET', '/organisateurs/le-bikini');
        self::assertSelectorExists('#a-venir .load-more[data-url="/organisateurs/le-bikini/a-venir/2"]');

        $crawler = $client->request('GET', '/organisateurs/le-bikini/a-venir/2');

        self::assertResponseIsSuccessful();
        self::assertSame(['Concert 7'], $crawler->filter('.card-body h3')->each(static fn ($title): string => trim($title->text())));
    }

    /**
     * The page keeps its URL when the publisher is renamed.
     */
    public function testARenamedPublisherKeepsItsUrl(): void
    {
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);

        $publisher->setName('Bikini Toulouse');
        save($publisher);

        self::assertSame('le-bikini', PublisherFactory::find(['id' => $publisher->getId()])->getSlug());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEvent(Publisher $publisher, string $date, array $attributes): Event
    {
        $day = new DateTimeImmutable($date);

        return EventFactory::createOne(['publisher' => $publisher, 'startDate' => $day, 'endDate' => $day, ...$attributes]);
    }
}
