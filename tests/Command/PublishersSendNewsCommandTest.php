<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\User;
use App\Enum\PublisherNewsEdition;
use App\Factory\PublisherFactory;
use App\Factory\PublisherNewsDeliveryFactory;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PublishersSendNewsCommandTest extends AppKernelTestCase
{
    public function testWithoutSendTheMembersAreOnlyCounted(): void
    {
        $this->publisher(new DateTimeImmutable('-1 year'));

        $tester = $this->execute(['editions' => ['2026-10-retour']]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 e-mail(s) to send', $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertSame(0, PublisherNewsDeliveryFactory::count());
    }

    public function testAnUnknownEditionIsRefused(): void
    {
        $tester = $this->execute(['editions' => ['1999-01']]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('2026-10-retour', $tester->getDisplay());
    }

    #[RequiresPhpExtension('mjml')]
    public function testTheComebackGoesOnceToThePublishersWhoStoppedComing(): void
    {
        $dormant = $this->publisher(new DateTimeImmutable('-1 year'));
        $this->publisher(new DateTimeImmutable('-1 month'));
        UserFactory::createOne(['lastLogin' => new DateTimeImmutable('-1 year')]);
        $this->publisher(new DateTimeImmutable('-1 year'), ['publisherNews' => false]);
        $this->publisher(new DateTimeImmutable('-1 year'), ['enabled' => false]);

        $this->execute(['editions' => ['2026-10-retour'], '--send' => true])->assertCommandIsSuccessful();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertEmailAddressContains($email, 'To', (string) $dormant->getEmail());
        self::assertEmailHeaderSame($email, 'Subject', PublisherNewsEdition::Comeback2026->getSubject());
        self::assertEmailHeaderSame($email, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        self::assertStringContainsString('/nouveautes-organisateurs/desinscription/' . $dormant->getId(), (string) $email->getHeaders()->getHeaderBody('List-Unsubscribe'));
        self::assertEmailHtmlBodyContains($email, 'By Night a bien changé');
        self::assertEmailTextBodyContains($email, 'Bonjour ' . $dormant->getUsername());
        self::assertSame(1, PublisherNewsDeliveryFactory::count(['user' => $dormant->getId(), 'edition' => '2026-10-retour']));

        // A run again goes on where the previous one stopped: nobody is left
        $tester = $this->execute(['editions' => ['2026-10-retour'], '--send' => true]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('0 e-mail(s) sent', $tester->getDisplay());
    }

    #[RequiresPhpExtension('mjml')]
    public function testTheMonthlyNewsGoesToThePublishersWhoCome(): void
    {
        $this->publisher(new DateTimeImmutable('-1 year'));
        $active = $this->publisher(new DateTimeImmutable('-1 month'));

        $this->execute(['editions' => ['2026-10'], '--send' => true])->assertCommandIsSuccessful();

        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', (string) $active->getEmail());
    }

    #[RequiresPhpExtension('mjml')]
    public function testTheLimitSplitsARun(): void
    {
        $this->publisher(new DateTimeImmutable('-1 month'));
        $this->publisher(new DateTimeImmutable('-1 month'));

        $this->execute(['editions' => ['2026-10'], '--send' => true, '--limit' => 1])->assertCommandIsSuccessful();

        self::assertEmailCount(1);
        self::assertSame(1, PublisherNewsDeliveryFactory::count());
    }

    #[RequiresPhpExtension('mjml')]
    public function testTheNextEditionStartsWithWhatTheLimitLeaves(): void
    {
        $this->publisher(new DateTimeImmutable('-1 year'));
        $active = $this->publisher(new DateTimeImmutable('-1 month'));
        $this->publisher(new DateTimeImmutable('-1 month'));

        // The comeback takes 1 of the 2 e-mails of the day, the monthly news the other
        $this->execute(['editions' => ['2026-10-retour', '2026-10'], '--send' => true, '--limit' => 2])->assertCommandIsSuccessful();

        self::assertEmailCount(2);
        self::assertSame(1, PublisherNewsDeliveryFactory::count(['edition' => '2026-10-retour']));
        self::assertSame(1, PublisherNewsDeliveryFactory::count(['user' => $active->getId(), 'edition' => '2026-10']));

        // The next day, the one left
        $this->execute(['editions' => ['2026-10-retour', '2026-10'], '--send' => true, '--limit' => 2])->assertCommandIsSuccessful();
        self::assertSame(2, PublisherNewsDeliveryFactory::count(['edition' => '2026-10']));
    }

    #[RequiresPhpExtension('mjml')]
    public function testAPreviewGoesToTheAddressAndRecordsNothing(): void
    {
        $member = $this->publisher(new DateTimeImmutable('-1 month'));

        $this->execute(['editions' => ['2026-10'], '--to' => 'me@example.com', '--as' => $member->getEmail()])->assertCommandIsSuccessful();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'me@example.com');
        self::assertEmailTextBodyContains($email, 'Bonjour ' . $member->getUsername());
        self::assertSame(0, PublisherNewsDeliveryFactory::count());
    }

    /**
     * A member who owns a publisher, last logged in on that date.
     *
     * @param array<string, mixed> $attributes
     */
    private function publisher(DateTimeImmutable $lastLogin, array $attributes = []): User
    {
        $user = UserFactory::createOne(['lastLogin' => $lastLogin, ...$attributes]);
        PublisherFactory::createOne(['owner' => $user]);

        return $user;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:publishers:send-news'));
        $tester->execute($input);

        return $tester;
    }
}
