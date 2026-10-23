<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\EventVisitStat;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Override;

/**
 * The raw audience of the event pages, a row per event and day (event_visit_stat): what app:events:sync-visits copied
 * from Umami, and the days before it read once from Google Analytics. Read only: the sync owns the rows.
 */
#[AdminRoute(path: '/event-visit-stat', name: 'event_visit_stat')]
final class EventVisitStatCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EventVisitStat::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
        ;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Statistique de visite')
            ->setEntityLabelInPlural('Statistiques de visite')
            ->setDefaultSort(['day' => 'DESC', 'visitors' => 'DESC'])
            ->setSearchFields(['event.name', 'event.id'])
        ;
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('event'))
            ->add(DateTimeFilter::new('day'))
            ->add(NumericFilter::new('visitors'))
            ->add(NumericFilter::new('ticketClicks'))
            ->add(BooleanFilter::new('fromGoogleAnalytics'))
        ;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID'),
            AssociationField::new('event', 'Événement'),
            DateField::new('day', 'Jour'),
            IntegerField::new('visitors', 'Visiteurs'),
            IntegerField::new('ticketClicks', 'Clics billetterie'),
            // Only the visitors who accepted Google Analytics' cookies, no ticketing clicks
            BooleanField::new('fromGoogleAnalytics', 'Google Analytics')->renderAsSwitch(false),
        ];
    }
}
