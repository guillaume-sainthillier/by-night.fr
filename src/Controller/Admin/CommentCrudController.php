<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\Comment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class CommentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Comment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Comment')
            ->setEntityLabelInPlural('Comment')
            ->setSearchFields(['commentaire', 'id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $event = AssociationField::new('event')->autocomplete();
        $user = AssociationField::new('user')->autocomplete();
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $approuve = BooleanField::new('approuve');
        $commentaire = TextareaField::new('commentaire');
        $parent = AssociationField::new('parent')->autocomplete();
        $reponses = AssociationField::new('reponses')->autocomplete();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $createdAt, $event, $user, $approuve];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $commentaire, $approuve, $createdAt, $updatedAt, $user, $event, $parent, $reponses];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$event, $user, $approuve, $commentaire];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$event, $user, $createdAt, $updatedAt, $approuve, $commentaire];
        }
        throw new \RuntimeException(sprintf('Unable to configure fields for page "%s"', $pageName));
    }
}
