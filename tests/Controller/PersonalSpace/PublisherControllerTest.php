<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Factory\EventFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class PublisherControllerTest extends AppWebTestCase
{
    public function testTheMemberEditsTheirPublishersPage(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $publisher = PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'page'));
        $client->submit($crawler->selectButton('Enregistrer')->form(), [
            'publisher[name]' => 'Le Bikini Toulouse',
            'publisher[description]' => 'Salle de concerts à Ramonville.',
            'publisher[website]' => 'lebikini.com',
            'publisher[socialLinks][instagram]' => 'https://www.instagram.com/lebikini',
        ]);

        self::assertResponseRedirects(self::organizerPath($user, 'page'));
        $saved = PublisherFactory::find(['id' => $publisher->getId()]);
        self::assertSame('Le Bikini Toulouse', $saved->getName());
        self::assertSame('Salle de concerts à Ramonville.', $saved->getDescription());
        self::assertSame('https://lebikini.com', $saved->getWebsite());
        self::assertSame(['instagram' => 'https://www.instagram.com/lebikini'], $saved->getSocialLinks());
        self::assertSame('le-bikini', $saved->getSlug());
    }

    public function testTheLogoIsShownOnThePublishersPage(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $publisher = PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $client->loginUser($user);
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(64, 64), $path);

        $crawler = $client->request('GET', self::organizerPath($user, 'page'));
        $form = $crawler->selectButton('Enregistrer')->form();
        $logo = $form['publisher[logoFile][file]'];
        self::assertInstanceOf(FileFormField::class, $logo);
        $logo->upload($path);
        $client->submit($form);

        self::assertResponseRedirects(self::organizerPath($user, 'page'));
        self::assertTrue(PublisherFactory::find(['id' => $publisher->getId()])->hasLogo());

        $client->request('GET', '/organisateurs/le-bikini');
        self::assertSelectorExists('aside .avatar img[alt="Le Bikini"]');
    }

    public function testALinkToAnotherNetworkIsRefused(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $publisher = PublisherFactory::createOne(['owner' => $user]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'page'));
        $client->submit($crawler->selectButton('Enregistrer')->form(), [
            'publisher[socialLinks][instagram]' => 'https://www.facebook.com/lebikini',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Ce lien ne mène pas à Instagram.');
        // The form changed the publisher in memory only: a fresh entity manager reads what was saved
        self::bootKernel();
        self::assertSame([], PublisherFactory::find(['id' => $publisher->getId()])->getSocialLinks());
    }

    /**
     * The pages without a publisher lead a member who has none to the creation of their first, named after them, then
     * to the page they asked for.
     */
    public function testAMemberWithoutAPublisherCreatesItInTheirName(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'Swing Toulouse']);
        $client->loginUser($user);

        $client->request('GET', '/espace-perso/imports');
        self::assertResponseRedirects('/espace-perso/nouvelle-page-organisateur?suite=imports');
        $crawler = $client->followRedirect();
        self::assertSame('Swing Toulouse', $crawler->filter('input[name="form[name]"]')->attr('value'));

        $client->submit($crawler->selectButton('Créer la page')->form());

        self::assertResponseRedirects('/espace-perso/organisateurs/swing-toulouse/imports');
        self::assertSame(1, PublisherFactory::count(['owner' => $user, 'name' => 'Swing Toulouse']));
    }

    public function testAMemberCreatesAnotherPublisher(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Swing Toulouse']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/nouvelle-page-organisateur?suite=evenements');
        self::assertSame('', (string) $crawler->filter('input[name="form[name]"]')->attr('value'));
        $client->submit($crawler->selectButton('Créer la page')->form(), ['form[name]' => 'Le Bikini']);

        self::assertResponseRedirects('/espace-perso/organisateurs/le-bikini/evenements');
        self::assertSame(2, PublisherFactory::count(['owner' => $user]));
    }

    /**
     * The former URLs and the links of the site lead to the member's first publisher, keeping their query.
     */
    public function testThePagesWithoutAPublisherLeadToTheMembersFirst(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Swing Toulouse']);
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $client->loginUser($user);

        $client->request('GET', '/espace-perso/mes-soirees?status=hidden');

        self::assertResponseRedirects('/espace-perso/organisateurs/swing-toulouse/evenements?status=hidden');
    }

    public function testAnEventsFormerUrlLeadsToItsPublishersPages(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'publisher' => PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini'])]);
        $client->loginUser($user);

        $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));

        self::assertResponseRedirects(\sprintf('/espace-perso/organisateurs/le-bikini/evenements/%d', $event->getId()), 301);
    }

    public function testAnEventsFormerStatisticsUrlLeadsToItsPublishersPages(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'publisher' => PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini'])]);
        $client->loginUser($user);

        $client->request('GET', \sprintf('/espace-perso/%d/statistiques?period=last_7_days', $event->getId()));

        self::assertResponseRedirects(\sprintf('/espace-perso/organisateurs/le-bikini/evenements/%d/statistiques?period=last_7_days', $event->getId()), 301);
    }

    /**
     * The switcher leads to the same page of each of the member's publishers.
     */
    public function testTheSwitcherLeadsToTheSamePageOfTheOtherPublishers(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Swing Toulouse']);
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/organisateurs/le-bikini/imports');

        self::assertResponseIsSuccessful();
        // In the navbar, next to the member's menu, as immobilus shows its active profile
        self::assertSelectorTextContains('.navbar .publisher-switcher .publisher-switcher-identity', 'Le Bikini');
        self::assertSame(
            ['/espace-perso/organisateurs/swing-toulouse/imports'],
            array_values(array_unique($crawler->filter('.publisher-switcher a[href*="/swing-toulouse/"]')->each(static fn ($link): string => (string) $link->attr('href')))),
        );
        // Its own pages stay on the publisher
        self::assertSelectorExists('.publisher-switcher a.active[href="/espace-perso/organisateurs/le-bikini/imports"]');
        self::assertSelectorExists('.publisher-switcher a[href="/espace-perso/organisateurs/le-bikini/exports"]');
    }

    /**
     * From one of a publisher's events, the switcher leads to the list of the others' events, which do not have it.
     */
    public function testFromAnEventTheSwitcherLeadsToTheOthersLists(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Swing Toulouse']);
        $event = EventFactory::createOne(['user' => $user, 'publisher' => PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini'])]);
        $client->loginUser($user);

        $client->request('GET', \sprintf('/espace-perso/organisateurs/le-bikini/evenements/%d', $event->getId()));

        self::assertSelectorExists('.publisher-switcher a[href="/espace-perso/organisateurs/swing-toulouse/evenements"]');
    }

    /**
     * Elsewhere on the site, the navbar has no publisher: the member's menu leads to their organizer pages.
     */
    public function testTheNavbarShowsAPublisherOnItsOrganizerPagesOnly(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $client->loginUser($user);

        $client->request('GET', '/espace-perso/mes-sorties');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.publisher-switcher');
    }

    /**
     * Its events move to the page chosen, which takes its URL; then the member is on that page's events.
     */
    public function testAPageIsDeletedWithItsEventsMovedToAnother(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $ramier = PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Ramier']);
        $bikini = PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $event = EventFactory::createOne(['user' => $user, 'publisher' => $ramier]);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/organisateurs/le-ramier/page');
        $client->submit($crawler->selectButton('Supprimer la page')->form(), [
            'publisher_delete[heir]' => (string) $bikini->getId(),
            'publisher_delete[name]' => 'Le Ramier',
        ]);

        self::assertResponseRedirects('/espace-perso/organisateurs/le-bikini/evenements');
        self::assertSame(0, PublisherFactory::count(['name' => 'Le Ramier']));
        self::assertSame($bikini->getId(), EventFactory::find(['id' => $event->getId()])->getPublisher()?->getId());

        $client->request('GET', '/organisateurs/le-ramier');
        self::assertResponseRedirects('/organisateurs/le-bikini', 301);
    }

    public function testAPageIsDeletedWithItsEvents(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $ramier = PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Ramier']);
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini']);
        $event = EventFactory::createOne(['user' => $user, 'publisher' => $ramier]);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/organisateurs/le-ramier/page');
        $client->submit($crawler->selectButton('Supprimer la page')->form(), [
            'publisher_delete[heir]' => 'delete',
            'publisher_delete[name]' => 'Le Ramier',
        ]);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertSame(0, EventFactory::count(['id' => $event->getId()]));

        $client->request('GET', '/organisateurs/le-ramier');
        self::assertResponseStatusCodeSame(410);
    }

    public function testAPageIsKeptWhenItsNameIsMistyped(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Ramier']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/organisateurs/le-ramier/page');
        $client->submit($crawler->selectButton('Supprimer la page')->form(), ['publisher_delete[name]' => 'Le Bikini']);

        self::assertResponseRedirects('/espace-perso/organisateurs/le-ramier/page#supprimer');
        self::assertSame(1, PublisherFactory::count(['name' => 'Le Ramier']));
    }

    /**
     * The member's only page keeps its events: they would have nowhere to go.
     */
    public function testTheOnlyPageWithEventsCannotBeDeleted(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        EventFactory::createOne(['user' => $user, 'publisher' => PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Ramier'])]);
        $client->loginUser($user);

        $client->request('GET', '/espace-perso/organisateurs/le-ramier/page');
        self::assertSelectorNotExists('form[name="publisher_delete"]');
        self::assertSelectorTextContains('#supprimer', 'C\'est votre seule page organisateur');

        $client->request('POST', '/espace-perso/organisateurs/le-ramier/supprimer', ['publisher_delete' => ['name' => 'Le Ramier']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, PublisherFactory::count(['name' => 'Le Ramier']));
    }

    public function testAnotherMembersPublisherIsOutOfReach(): void
    {
        $client = self::createClient();
        PublisherFactory::createOne(['name' => 'Le Bikini']);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/espace-perso/organisateurs/le-bikini/evenements');
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/espace-perso/organisateurs/inconnu/evenements');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * An event is reached on the pages of its own publisher only, even among the member's.
     */
    public function testAnEventIsNotReachedThroughAnotherPublisherOfTheMember(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'name' => 'Swing Toulouse']);
        $event = EventFactory::createOne(['user' => $user, 'publisher' => PublisherFactory::createOne(['owner' => $user, 'name' => 'Le Bikini'])]);
        $client->loginUser($user);

        $client->request('GET', \sprintf('/espace-perso/organisateurs/swing-toulouse/evenements/%d', $event->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testThePageSaysWhatIsLeftToComplete(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        PublisherFactory::createOne(['owner' => $user, 'description' => 'Salle de concerts.']);
        $client->loginUser($user);

        $client->request('GET', self::organizerPath($user, 'page'));

        self::assertSelectorTextContains('.card', 'Complète à 20');
        self::assertSelectorTextContains('.card', 'Un logo');
    }

    public function testAVisitorIsAskedToLogIn(): void
    {
        $client = self::createClient();

        $client->request('GET', '/espace-perso/page-organisateur');

        self::assertResponseRedirects();
    }
}
