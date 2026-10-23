<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Analytics\AudienceQuery;
use App\Enum\AudiencePeriod;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The period of an event's statistics (personal-space/stats.html.twig): its shortcut chips, or dates picked, which win.
 */
final class AudienceQueryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // The shortcut of the chips, emptied by assets/js/pages/event_stats.js when dates are picked
            ->add('period', HiddenType::class, ['required' => false])
            ->add('dateRange', DateRangeType::class, [
                'label' => 'Dates',
                'placeholder' => 'Choisir des dates…',
            ]);

        // An unknown shortcut is the default one
        $builder->get('period')->addModelTransformer(new CallbackTransformer(
            static fn (?AudiencePeriod $period): string => $period?->value ?? '',
            static fn (?string $period): ?AudiencePeriod => AudiencePeriod::tryFrom((string) $period),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
            'data_class' => AudienceQuery::class,
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
