<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\ParserHistory;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use RuntimeException;

#[AdminRoute(path: '/parser-history', name: 'parser_history')]
final class ParserHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParserHistory::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW)
        ;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Historique')
            ->setEntityLabelInPlural('Historiques')
            ->setSearchFields(['fromData', 'nouvellesSoirees', 'updateSoirees', 'explorations', 'id']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $startDate = DateTimeField::new('startDate');
        $fromData = TextField::new('fromData');
        $endDate = DateTimeField::new('endDate');
        $nouvellesSoirees = IntegerField::new('nouvellesSoirees');
        $updateSoirees = IntegerField::new('updateSoirees');
        $explorations = IntegerField::new('explorations');
        $id = IdField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $startDate, $fromData, $endDate, $nouvellesSoirees, $updateSoirees, $explorations];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $startDate, $fromData, $endDate, $nouvellesSoirees, $updateSoirees, $explorations];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$startDate, $fromData, $endDate, $nouvellesSoirees, $updateSoirees, $explorations];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$startDate, $fromData, $endDate, $nouvellesSoirees, $updateSoirees, $explorations];
        }

        throw new RuntimeException(\sprintf('Unable to configure fields for page "%s"', $pageName));
    }
}
