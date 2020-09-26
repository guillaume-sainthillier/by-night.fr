<?php

namespace App\Controller\Admin;

use App\Entity\Place;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PlaceCrudController extends AbstractCrudController
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
            ->setSearchFields(['id', 'externalId', 'ville', 'codePostal', 'facebookId', 'rue', 'latitude', 'longitude', 'nom', 'slug', 'path', 'url']);
    }

    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addPanel('Informations');
        $id = IdField::new('id', 'ID');
        $externalId = TextField::new('externalId');
        $slug = TextField::new('slug');
        $nom = TextField::new('nom');
        $facebookId = TextField::new('facebookId');
        $junk = BooleanField::new('junk');
        $panel2 = FormField::addPanel('Lieu');
        $rue = TextField::new('rue');
        $codePostal = TextField::new('codePostal');
        $ville = TextField::new('ville');
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
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $externalId, $ville, $codePostal, $facebookId, $junk, $rue, $latitude, $longitude, $nom, $slug, $path, $url, $createdAt, $updatedAt, $city, $country];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$panel1, $id, $externalId, $slug, $nom, $facebookId, $junk, $panel2, $rue, $codePostal, $ville, $latitude, $longitude, $city, $country];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$panel1, $id, $externalId, $slug, $nom, $facebookId, $junk, $panel2, $rue, $codePostal, $ville, $latitude, $longitude, $city, $country];
        }
    }
}
