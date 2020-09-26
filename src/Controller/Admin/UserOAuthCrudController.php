<?php

namespace App\Controller\Admin;

use App\Entity\UserOAuth;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class UserOAuthCrudController extends OAuthCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserOAuth::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('User social')
            ->setEntityLabelInPlural('User sociaux');
    }
}
