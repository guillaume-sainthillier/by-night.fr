<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\Tag;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;

#[AdminRoute(path: '/tag', name: 'tag')]
final class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tag')
            ->setEntityLabelInPlural('Tags')
            ->setSearchFields([
                'id',
                'name',
                'slug',
            ]);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $name = TextField::new('name');
        $slug = TextField::new('slug');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $slug];
        }

        return [
            $id->hideOnForm(),
            $name,
            $slug,
        ];
    }
}
