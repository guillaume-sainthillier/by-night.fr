<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Admin\Filter;

use App\Entity\AdminZone1;
use App\Entity\AdminZone2;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * Filters places on the region (AdminZone1) of their city, which hangs from it directly or through its
 * département (AdminZone2).
 */
final class RegionFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => AdminZone1::class,
                'required' => false,
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('r')
                    ->orderBy('r.name', 'ASC'),
                'choice_label' => static fn (AdminZone1 $region): string => \sprintf('%s (%s)', $region->getName(), $region->getCountry()?->getId()),
                'choice_translation_domain' => false,
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $region = $filterDataDto->getValue();
        if (!$region instanceof AdminZone1) {
            return;
        }

        $cityAlias = 'region_city_' . $filterDataDto->getParameterName();
        $parameter = $filterDataDto->getParameterName();

        $queryBuilder
            ->join(\sprintf('%s.city', $filterDataDto->getEntityAlias()), $cityAlias)
            ->andWhere(\sprintf(
                '%1$s.parent = :%2$s OR %1$s.parent IN (SELECT d_%2$s FROM %3$s d_%2$s WHERE d_%2$s.parent = :%2$s)',
                $cityAlias,
                $parameter,
                AdminZone2::class,
            ))
            ->setParameter($parameter, $region->getId());
    }
}
