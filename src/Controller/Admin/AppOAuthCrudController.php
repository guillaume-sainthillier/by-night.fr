<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
