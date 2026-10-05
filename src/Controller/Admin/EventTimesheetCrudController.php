<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\EventTimesheet;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use Override;

#[AdminRoute(path: '/event-timesheet', name: 'event_timesheet')]
final class EventTimesheetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EventTimesheet::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Date')
            ->setEntityLabelInPlural('Dates')
            ->setSearchFields(['id', 'hours', 'event.name']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $event = AssociationField::new('event')->autocomplete();
        $sourceEvent = AssociationField::new('sourceEvent', 'Hérité de')
            ->autocomplete()
            ->setHelp("Renseigné sur les dates qu'un événement principal hérite d'un doublon de sa famille\u{a0}: elles suivent ce doublon et disparaissent avec lui.");
        $startAt = DateTimeField::new('startAt', 'Début');
        $endAt = DateTimeField::new('endAt', 'Fin');
        $startTime = TimeField::new('startTime', 'Heure de début');
        $endTime = TimeField::new('endTime', 'Heure de fin');
        $hours = TextField::new('hours', 'Horaires affichés')->setHelp('Seulement ce que les heures ne disent pas');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $event, $sourceEvent, $startAt, $endAt, $startTime, $endTime, $hours];
        }

        return [
            $id->hideOnForm(),
            $event,
            $sourceEvent,
            $startAt,
            $endAt,
            $startTime,
            $endTime,
            $hours,
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
        ];
    }
}
