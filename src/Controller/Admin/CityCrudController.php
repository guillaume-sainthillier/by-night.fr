<?php

namespace App\Controller\Admin;

use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class CityCrudController extends AdminZoneCrudController
{
    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ville')
            ->setEntityLabelInPlural('Villes')
            ->setSearchFields(['id', 'slug', 'name', 'latitude', 'longitude', 'population', 'admin1Code', 'admin2Code']);
    }
}
