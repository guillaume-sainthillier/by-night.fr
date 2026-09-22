<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Search\SearchEvent;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SearchType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateRange', DateRangeType::class, [
                'label' => "Quand\u{a0}?",
            ])
            ->add('range', NumberType::class, [
                'html5' => true,
                'label' => 'Rayon (km)',
                'attr' => ['placeholder' => "Dans quel rayon cherchez-vous\u{a0}?"],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => $options['types_manif'],
                'label' => "Quoi\u{a0}?",
                'multiple' => true,
                'expanded' => false,
                'disabled' => [] === $options['types_manif'],
                'help' => [] === $options['types_manif'] ? 'Aucun type d\'événement disponible pour cette localisation.' : null,
                'required' => false,
                'placeholder' => 'Tous les types',
                'attr' => [
                    'placeholder' => 'Tous les types',
                    'size' => 1,
                ], ])
            ->add('term', TextType::class, [
                'required' => false,
                'label' => 'Mots-clés',
                'attr' => ['placeholder' => "Quel événement cherchez-vous\u{a0}?"], ])
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
            'types_manif' => [],
            'data_class' => SearchEvent::class,
            'csrf_protection' => false,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
