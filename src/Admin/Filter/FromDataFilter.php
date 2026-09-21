<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Admin\Filter;

use Closure;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Filters events on their import source (Event::$fromData), events created by hand having none.
 */
final class FromDataFilter implements FilterInterface
{
    use FilterTrait;

    public const string NO_SOURCE = '__none__';

    /**
     * @param Closure(): array<string, string> $choices label => source, only called when the filter form is rendered or submitted
     */
    public static function new(string $propertyName, Closure $choices, ?string $label = null): self
    {
        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions([
                'required' => false,
                'choice_loader' => new CallbackChoiceLoader(
                    static fn (): array => ['Aucune (saisie manuelle)' => self::NO_SOURCE] + $choices(),
                ),
                'choice_translation_domain' => false,
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $source = $filterDataDto->getValue();
        if (null === $source || '' === $source) {
            return;
        }

        $property = \sprintf('%s.%s', $filterDataDto->getEntityAlias(), $filterDataDto->getProperty());
        if (self::NO_SOURCE === $source) {
            $queryBuilder->andWhere(\sprintf('%s IS NULL', $property));

            return;
        }

        $queryBuilder
            ->andWhere(\sprintf('%s = :%s', $property, $filterDataDto->getParameterName()))
            ->setParameter($filterDataDto->getParameterName(), $source);
    }
}
