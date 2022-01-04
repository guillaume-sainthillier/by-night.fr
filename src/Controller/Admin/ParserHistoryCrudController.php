<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\ParserHistory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ParserHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParserHistory::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW)
            ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Historique')
            ->setEntityLabelInPlural('Historiques')
            ->setSearchFields(['fromData', 'nouvellesSoirees', 'updateSoirees', 'explorations', 'id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $dateDebut = DateTimeField::new('dateDebut');
        $fromData = TextField::new('fromData');
        $dateFin = DateTimeField::new('dateFin');
        $nouvellesSoirees = IntegerField::new('nouvellesSoirees');
        $updateSoirees = IntegerField::new('updateSoirees');
        $explorations = IntegerField::new('explorations');
        $id = IdField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $dateDebut, $fromData, $dateFin, $nouvellesSoirees, $updateSoirees, $explorations];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $dateDebut, $fromData, $dateFin, $nouvellesSoirees, $updateSoirees, $explorations];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$dateDebut, $fromData, $dateFin, $nouvellesSoirees, $updateSoirees, $explorations];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$dateDebut, $fromData, $dateFin, $nouvellesSoirees, $updateSoirees, $explorations];
        }
    }
}
