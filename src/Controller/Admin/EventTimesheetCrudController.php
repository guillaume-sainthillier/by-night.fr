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
use Override;

#[AdminRoute(routePath: '/event-timesheet', routeName: 'event_timesheet')]
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
            ->setEntityLabelInSingular('Timesheet')
            ->setEntityLabelInPlural('Timesheets')
            ->setSearchFields(['id', 'hours', 'event.name']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $event = AssociationField::new('event')->autocomplete();
        $startAt = DateTimeField::new('startAt', 'Début');
        $endAt = DateTimeField::new('endAt', 'Fin');
        $hours = TextField::new('hours', 'Horaires affichés');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $event, $startAt, $endAt, $hours];
        }

        return [
            $id->hideOnForm(),
            $event,
            $startAt,
            $endAt,
            $hours,
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
        ];
    }
}
