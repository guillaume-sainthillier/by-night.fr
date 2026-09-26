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
use App\Entity\Country;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;

#[AdminRoute(path: '/country', name: 'country')]
final class CountryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Country::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pays')
            ->setEntityLabelInPlural('Pays')
            ->setSearchFields([
                'id',
                'slug',
                'locale',
                'name',
                'displayName',
                'atDisplayName',
                'capital',
                'postalCodeRegex',
                'headline',
            ]);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('featured');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $identityPanel = FormField::addFieldset('Identité');
        // The code is the primary key every city, place and event of the country points to:
        // only set on creation
        $id = TextField::new('id', 'Code pays')
            ->setHelp('Code ISO 3166-1 alpha-2 en majuscules (FR, BE, CH…).')
            ->setFormTypeOption('disabled', Crud::PAGE_EDIT === $pageName);
        $locale = TextField::new('locale');
        $name = TextField::new('name');
        $displayName = TextField::new('displayName');
        $atDisplayName = TextField::new('atDisplayName');
        $capital = TextField::new('capital');
        $postalCodeRegex = TextField::new('postalCodeRegex');
        $slug = TextField::new('slug');

        $portalPanel = FormField::addFieldset('Portail');
        $headline = TextField::new('headline', 'Accroche')
            ->setHelp('Une phrase sous le nom du pays, en tête de son portail.');
        $description = TextareaField::new('description', 'Description')
            ->setNumOfRows(6)
            ->setHelp("Présentation du pays affichée sur son portail, en Markdown\u{a0}: **gras**, *italique*, [lien](https://…), une ligne vide entre deux paragraphes.");
        $heroImage = VichImageField::new('heroImageFile', 'Image de couverture');
        $heroCaption = TextField::new('heroCaption', "Légende de l'image")
            ->setHelp('Légende ou crédit photo affiché sur l’image de couverture.');
        $featured = BooleanField::new('featured', 'Mis en avant');
        $displayOrder = IntegerField::new('displayOrder', "Ordre d'affichage")
            ->setHelp('Les plus petits en premier. Laissez vide pour ne pas classer le pays.');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $heroImage, $displayName, $atDisplayName, $featured, $displayOrder];
        }

        return [
            $identityPanel,
            $id,
            $slug,
            $locale,
            $name,
            $displayName,
            $atDisplayName,
            $capital,
            $postalCodeRegex,
            $portalPanel,
            $headline,
            $description,
            $heroImage,
            $heroCaption,
            $featured,
            $displayOrder,
        ];
    }
}
