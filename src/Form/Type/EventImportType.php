<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Transfer\Sheet\SheetReader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * The spreadsheet a member imports: a CSV or an XLSX, light enough for the rows an import reads at most.
 */
final class EventImportType extends AbstractType
{
    public const string MAX_SIZE = '2M';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Votre fichier',
            'help' => \sprintf('CSV ou Excel (XLSX), %d lignes et 2 Mo au plus.', SheetReader::MAX_ROWS),
            'attr' => ['accept' => '.csv,.xlsx'],
            'constraints' => [
                new NotNull(message: 'Choisissez un fichier.'),
                new File(
                    maxSize: self::MAX_SIZE,
                    // Browsers and systems name a CSV's type in many ways: its extension decides
                    extensions: [
                        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
                        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
                    ],
                    extensionsMessage: 'Importez un fichier CSV ou Excel (XLSX).',
                    maxSizeMessage: 'Le fichier pèse {{ size }} {{ suffix }}, 2 Mo au plus : découpez-le en plusieurs imports.',
                ),
            ],
        ]);
    }
}
