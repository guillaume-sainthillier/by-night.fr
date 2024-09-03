<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\Place;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class PlaceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Place::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Place')
            ->setEntityLabelInPlural('Places')
            ->setSearchFields([
                'id',
                'name',
                'metadatas.externalId',
                'metadatas.externalOrigin',
                'cityName',
                'cityPostalCode',
                'facebookId',
                'street',
                'latitude',
                'longitude',
                'slug',
                'path',
                'url',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addPanel('Informations');
        $id = IdField::new('id', 'ID');
        $externalId = CollectionField::new('metadatas');
        $slug = TextField::new('slug');
        $nom = TextField::new('name');
        $facebookId = TextField::new('facebookId');
        $junk = BooleanField::new('junk');
        $panel2 = FormField::addPanel('Lieu');
        $rue = TextField::new('street');
        $codePostal = TextField::new('cityPostalCode');
        $ville = TextField::new('cityName');
        $latitude = NumberField::new('latitude');
        $longitude = NumberField::new('longitude');
        $city = AssociationField::new('city')->autocomplete();
        $country = AssociationField::new('country')->autocomplete();
        $path = TextField::new('path');
        $url = TextField::new('url');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $nom, $city, $country];
        }

        return [
            $panel1,
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
            $slug,
            $nom,
            $externalId,
            $facebookId,
            $junk,
            $path,
            $url,
            $panel2,
            $rue,
            $codePostal,
            $ville,
            $latitude,
            $longitude,
            $city,
            $country,
        ];
    }
}
