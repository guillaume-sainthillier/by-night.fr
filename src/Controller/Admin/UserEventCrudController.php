<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\UserEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use RuntimeException;

class UserEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Agenda')
            ->setEntityLabelInPlural('Agendas')
            ->setSearchFields(['id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $updatedAt = DateTimeField::new('updatedAt');
        $event = AssociationField::new('event')->autocomplete();
        $user = AssociationField::new('user')->autocomplete();
        $participe = BooleanField::new('participe');
        $interet = BooleanField::new('interet');
        $createdAt = DateTimeField::new('createdAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $updatedAt, $event, $user, $participe, $interet];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $participe, $interet, $createdAt, $updatedAt, $user, $event];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$event, $user, $participe, $interet];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$updatedAt, $event, $user, $participe, $interet];
        }
        throw new RuntimeException(sprintf('Unable to configure fields for page "%s"', $pageName));
    }
}
