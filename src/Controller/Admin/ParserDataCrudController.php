<?php

namespace App\Controller\Admin;

use App\Entity\ParserData;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ParserDataCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParserData::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Exploration')
            ->setEntityLabelInPlural('Explorations')
            ->setSearchFields(['externalId', 'reason', 'firewallVersion', 'parserVersion', 'id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $externalId = TextField::new('externalId');
        $lastUpdated = DateTimeField::new('lastUpdated');
        $reason = IntegerField::new('reason');
        $firewallVersion = TextField::new('firewallVersion');
        $parserVersion = TextField::new('parserVersion');
        $id = IdField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $externalId, $lastUpdated, $reason, $firewallVersion, $parserVersion];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $externalId, $lastUpdated, $reason, $firewallVersion, $parserVersion];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$id, $externalId, $lastUpdated, $reason, $firewallVersion, $parserVersion];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$id, $externalId, $lastUpdated, $reason, $firewallVersion, $parserVersion];
        }
    }
}
