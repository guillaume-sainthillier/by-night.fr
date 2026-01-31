<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\UserEvent;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Override;

#[AdminRoute(path: '/user-event', name: 'user_event')]
final class UserEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserEvent::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Agenda')
            ->setEntityLabelInPlural('Agendas')
            ->setSearchFields(['id']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $updatedAt = DateTimeField::new('updatedAt');
        $event = AssociationField::new('event')->autocomplete();
        $user = AssociationField::new('user')->autocomplete();
        $participe = BooleanField::new('going');
        $interet = BooleanField::new('wish');
        $createdAt = DateTimeField::new('createdAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $updatedAt, $event, $user, $participe, $interet];
        }

        return [
            $id,
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
            $participe,
            $interet,
            $user,
            $event,
        ];
    }
}
