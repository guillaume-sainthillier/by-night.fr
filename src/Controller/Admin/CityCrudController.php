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
use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;

#[AdminRoute(path: '/city', name: 'city')]
final class CityCrudController extends AdminZoneCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ville')
            ->setEntityLabelInPlural('Villes')
            ->setSearchFields(['id', 'slug', 'name', 'latitude', 'longitude', 'population', 'admin1Code', 'admin2Code']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('metropolis')
            ->add('country');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $geographyFields = parent::configureFields($pageName);

        $metropolis = BooleanField::new('metropolis', 'Métropole');
        $displayOrder = IntegerField::new('displayOrder', "Ordre d'affichage")
            ->setHelp('Les plus petites en premier. Laissez vide pour ne pas classer la ville.');

        if (Crud::PAGE_INDEX === $pageName) {
            return [...$geographyFields, $metropolis, $displayOrder];
        }

        return [
            FormField::addFieldset('Géographie'),
            ...$geographyFields,
            FormField::addFieldset('Page de la ville'),
            TextField::new('headline', 'Accroche')
                ->setHelp('Une phrase sous le nom de la ville, en tête de sa page.'),
            TextareaField::new('description', 'Description')
                ->setNumOfRows(6)
                ->setHelp("Présentation de la ville affichée sur sa page, en Markdown\u{a0}: **gras**, *italique*, [lien](https://…), une ligne vide entre deux paragraphes."),
            VichImageField::new('heroImageFile', 'Image de couverture'),
            $metropolis,
            $displayOrder,
        ];
    }
}
