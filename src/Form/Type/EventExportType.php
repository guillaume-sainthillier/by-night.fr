<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Enum\EventExportScope;
use App\Enum\TransferFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Which of their events a member exports, and as what.
 */
final class EventExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scope', EnumType::class, [
                'class' => EventExportScope::class,
                'label' => 'Événements',
                'choice_label' => static fn (EventExportScope $scope): string => $scope->getLabel(),
                'data' => EventExportScope::All,
            ])
            ->add('format', EnumType::class, [
                'class' => TransferFormat::class,
                'label' => 'Format',
                'choice_label' => static fn (TransferFormat $format): string => $format->getLabel(),
                'data' => TransferFormat::Xlsx,
            ])
            // The search and the status filter of the list the export was started from ("Exporter" by the table)
            ->add('searchQuery', HiddenType::class, ['required' => false])
            ->add('statusFilter', HiddenType::class, ['required' => false]);
    }
}
