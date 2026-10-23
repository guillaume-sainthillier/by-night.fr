<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Admin\Field\VichImageField;
use App\Entity\Publisher;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;

#[AdminRoute(path: '/publisher', name: 'publisher')]
final class PublisherCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Publisher::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Organisateur')
            ->setEntityLabelInPlural('Organisateurs')
            ->setSearchFields(['id', 'name', 'slug'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    /**
     * Not deleted here: its events would stay without anyone to edit them. Its members delete it from its page, moving
     * or deleting its events (App\Manager\PublisherRemover).
     */
    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DELETE, Action::BATCH_DELETE);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(EntityFilter::new('owner'));
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield TextField::new('slug')->hideOnForm();
        yield AssociationField::new('owner', 'Propriétaire')->autocomplete();
        yield VichImageField::new('logoFile', 'Logo')->hideOnIndex();
        yield VichImageField::new('coverFile', 'Couverture')->hideOnIndex();
        yield TextareaField::new('description', 'Présentation')->hideOnIndex();
        yield UrlField::new('website', 'Site web')->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
    }
}
