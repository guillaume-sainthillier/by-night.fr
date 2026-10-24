<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The comeback's first run (2026-10-24) hard-bounced a tenth of the addresses last logged in before 2018, against 1
 * in 27 of the later ones, and the addresses made up for the former Twitter and Facebook logins reach no mail server:
 * the publishers still without the news with such an address are unsubscribed from it (51 in production).
 */
final class Version20261024101431 extends AbstractMigration
{
    /** The publishers still without any news, subscribed, whose address is too old or made up */
    private const string UNREACHABLE = <<<'SQL'
        id IN (SELECT owner_id FROM publisher)
        AND id NOT IN (SELECT user_id FROM publisher_news_delivery)
        AND (last_login IS NULL OR last_login < '2018-01-01' OR email LIKE '%@twitter.fr' OR email LIKE '%@facebook.fr')
        SQL;

    public function getDescription(): string
    {
        return 'Unsubscribe from the publishers\' news the publishers whose address is too old or made up';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE `user` SET publisher_news = 0 WHERE publisher_news = 1 AND ' . self::UNREACHABLE);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE `user` SET publisher_news = 1 WHERE publisher_news = 0 AND ' . self::UNREACHABLE);
    }
}
