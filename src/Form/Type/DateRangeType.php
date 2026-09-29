<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Search\DateRange;
use DateTimeInterface;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A compound form type for date range selection.
 *
 * This type creates three fields:
 * - Two hidden DateType fields (from/to) that store the actual dates
 * - One visible text field (range) for the date picker UI
 *
 * Uses Symfony's built-in DateType for proper date handling and transformation.
 */
final class DateRangeType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fromField = $options['from_field'];
        $toField = $options['to_field'];

        $dateOptions = [
            'widget' => 'single_text',
            'html5' => false,
            'label' => false,
            'input' => $options['input'],
            'model_timezone' => $options['model_timezone'],
            'view_timezone' => $options['view_timezone'],
        ];

        $builder->add('from', DateType::class, [
            ...$dateOptions,
            'property_path' => $fromField,
        ]);

        if (null !== $toField) {
            $builder->add('to', DateType::class, [
                ...$dateOptions,
                'property_path' => $toField,
            ]);
        }

        $builder->add('range', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => $options['label'],
            'attr' => array_filter([
                'class' => 'shorcuts_date',
                'autocomplete' => 'off',
                'placeholder' => $options['placeholder'],
                'data-single-date' => $options['single_date_picker'] ? 'true' : null,
            ], static fn (?string $value): bool => null !== $value),
        ]);
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Make date fields render as hidden inputs
        $view->children['from']->vars['type'] = 'hidden';
        if (isset($view->children['to'])) {
            $view->children['to']->vars['type'] = 'hidden';
        }

        // Inject JS data attributes for the date picker
        $view->children['range']->vars['attr']['data-from'] = $view->children['from']->vars['id'];
        if (isset($view->children['to'])) {
            $view->children['range']->vars['attr']['data-to'] = $view->children['to']->vars['id'];
        }

        // The label of the dates picked, as the date picker writes it
        /** @var DateTimeInterface|null $from */
        $from = $form->get('from')->getData();
        /** @var DateTimeInterface|null $to */
        $to = $form->has('to') ? $form->get('to')->getData() : null;

        if (null !== $from) {
            $view->children['range']->vars['value'] = new DateRange($from, $to)->label();
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'from_field' => 'from',
            'to_field' => 'to',
            'label' => "Quand\u{a0}?",
            'placeholder' => null,
            'single_date_picker' => false,
            // DateType options (passed through to child fields)
            'input' => 'datetime',
            'model_timezone' => null,
            'view_timezone' => null,
        ]);

        $resolver->setAllowedTypes('from_field', 'string');
        $resolver->setAllowedTypes('to_field', ['string', 'null']);
        $resolver->setAllowedTypes('placeholder', ['string', 'null']);
        $resolver->setAllowedTypes('single_date_picker', 'bool');
        $resolver->setAllowedTypes('input', 'string');
        $resolver->setAllowedTypes('model_timezone', ['string', 'null']);
        $resolver->setAllowedTypes('view_timezone', ['string', 'null']);

        $resolver->setAllowedValues('input', ['datetime', 'datetime_immutable', 'string', 'timestamp', 'array']);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'date_range';
    }
}
