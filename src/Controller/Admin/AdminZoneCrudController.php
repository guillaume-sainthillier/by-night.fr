<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\AdminZone;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AdminZoneCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AdminZone::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Admin Zone')
            ->setEntityLabelInPlural('Admin Zones')
            ->setSearchFields(['id', 'slug', 'name', 'latitude', 'longitude', 'population', 'admin1Code', 'admin2Code']);
    }

    public function configureFields(string $pageName): iterable
    {
        $slug = TextField::new('slug');
        $name = TextField::new('name');
        $latitude = NumberField::new('latitude');
        $longitude = NumberField::new('longitude');
        $population = IntegerField::new('population');
        $admin1Code = TextField::new('admin1Code');
        $admin2Code = TextField::new('admin2Code');
        $country = AssociationField::new('country');
        $parent = AssociationField::new('parent')->autocomplete();
        $id = IdField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $latitude, $longitude, $population, $admin1Code, $admin2Code];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $slug, $name, $latitude, $longitude, $population, $admin1Code, $admin2Code, $country, $parent];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$slug, $name, $latitude, $longitude, $population, $admin1Code, $admin2Code, $country, $parent];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$slug, $name, $latitude, $longitude, $population, $admin1Code, $admin2Code, $country, $parent];
        }
    }
}
