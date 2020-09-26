<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\Country;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CountryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Country::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pays')
            ->setEntityLabelInPlural('Pays')
            ->setSearchFields(['id', 'slug', 'locale', 'name', 'displayName', 'atDisplayName', 'capital', 'postalCodeRegex']);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = TextField::new('id', 'ID');
        $locale = TextField::new('locale');
        $name = TextField::new('name');
        $displayName = TextField::new('displayName');
        $atDisplayName = TextField::new('atDisplayName');
        $capital = TextField::new('capital');
        $postalCodeRegex = TextField::new('postalCodeRegex');
        $slug = TextField::new('slug');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $displayName];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $slug, $locale, $name, $displayName, $atDisplayName, $capital, $postalCodeRegex];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$id, $locale, $name, $displayName, $atDisplayName, $capital, $postalCodeRegex];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$id, $locale, $name, $displayName, $atDisplayName, $capital, $postalCodeRegex];
        }
    }
}
