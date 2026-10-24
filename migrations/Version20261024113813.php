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
 * The warned events a member leaves out of an import are kept by their reference, no longer by their row: the import
 * reads the check's file of kept rows, numbered afresh, so a refused or blank row above shifted the choice onto the
 * next event. The rows already chosen (an import to resume) become the references their warnings name.
 */
final class Version20261024113813 extends AbstractMigration
{
    /** The warnings of an import, one per line: its row and the reference of its event */
    private const string WARNINGS = "JSON_TABLE(i.warnings, '$[*]' COLUMNS (line INT PATH '$.row', reference VARCHAR(255) PATH '$.reference')) w";

    public function getDescription(): string
    {
        return 'Keep the warned events left out of an import by their reference, not their row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import RENAME COLUMN skipped_rows TO skipped_references');
        $this->addSql('UPDATE event_import i SET i.skipped_references = (SELECT JSON_ARRAYAGG(w.reference) FROM ' . self::WARNINGS . ' WHERE w.reference IS NOT NULL AND JSON_CONTAINS(i.skipped_references, CAST(w.line AS JSON))) WHERE i.skipped_references IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE event_import i SET i.skipped_references = (SELECT JSON_ARRAYAGG(w.line) FROM ' . self::WARNINGS . ' WHERE JSON_CONTAINS(i.skipped_references, JSON_QUOTE(w.reference))) WHERE i.skipped_references IS NOT NULL');
        $this->addSql('ALTER TABLE event_import RENAME COLUMN skipped_references TO skipped_rows');
    }
}
