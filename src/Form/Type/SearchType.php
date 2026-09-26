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
use App\Search\SearchEvent;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
            // The date shortcut of the chips (location/agenda/_filters.html.twig), kept when the form is sent again; the
            // dates picked win (SearchEvent::getPreset())
            ->add('when', HiddenType::class, ['required' => false])
            // Custom dates: the shortcuts are the chips below it
            ->add('dateRange', DateRangeType::class, [
                'label' => "Quand\u{a0}?",
                'placeholder' => 'Choisir des dates…',
            ])
            ->add('range', NumberType::class, [
                'html5' => true,
                'label' => 'Rayon (km)',
                // assets/js/pages/agenda.js turns it into a slider snapping to 5, 10, 25, 50 and 100 km
                'attr' => [
                    'placeholder' => "Dans quel rayon cherchez-vous\u{a0}?",
                    'min' => 5,
                    'max' => SearchEvent::MAX_RANGE,
                    'data-slider' => 'km',
                ],
            ])
            ->add('term', TextType::class, [
                'required' => false,
                'label' => 'Mots-clés',
                'attr' => ['placeholder' => 'Un concert, une expo, un lieu…'], ])
        ;

        // An unknown shortcut is no shortcut
        $builder->get('when')->addModelTransformer(new CallbackTransformer(
            static fn (?DateRangePreset $when): string => $when?->value ?? '',
            static fn (?string $when): ?DateRangePreset => DateRangePreset::tryFrom((string) $when),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
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
