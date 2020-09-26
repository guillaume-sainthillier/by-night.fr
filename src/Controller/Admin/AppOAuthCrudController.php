<?php

namespace App\Controller\Admin;

use App\Entity\AppOAuth;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class AppOAuthCrudController extends OAuthCrudController
{
    public static function getEntityFqcn(): string
    {
        return AppOAuth::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('App social')
            ->setEntityLabelInPlural('App sociaux');
    }
}