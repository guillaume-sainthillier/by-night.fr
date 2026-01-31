<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Override;

#[AdminRoute(routePath: '/city', routeName: 'city')]
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
}
