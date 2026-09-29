<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Enum\DateRangePreset;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * The search of the home, city and country pages: what, and when (a preset period). Unnamed and sent by GET to the
 * agenda of the location, whose filters read "?term=jazz&when=this_weekend" (SearchType).
 */
final class QuickSearchType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('term', TextType::class, [
                'required' => false,
                'label' => "Que voulez-vous faire\u{a0}?",
                'attr' => ['placeholder' => 'Concert, expo, marché, théâtre…'],
                'constraints' => [new Length(max: 100)],
            ])
            ->add('when', EnumType::class, [
                'class' => DateRangePreset::class,
                'label' => "Quand\u{a0}?",
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'choice_label' => static fn (DateRangePreset $preset): string => $preset->getLabel(),
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
