<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use DateTime;
use DateTimeInterface;
use IntlDateFormatter;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
    private const array PRESET_RANGES = [
        "N'importe quand" => ['modifier' => 'now', 'to_modifier' => null],
        "Aujourd'hui" => ['modifier' => 'now', 'to_modifier' => 'now'],
        'Demain' => ['modifier' => 'tomorrow', 'to_modifier' => 'tomorrow'],
        'Ce week-end' => ['modifier' => 'friday this week', 'to_modifier' => 'sunday this week'],
        'Cette semaine' => ['modifier' => 'monday this week', 'to_modifier' => 'sunday this week'],
        'Ce mois' => ['modifier' => 'first day of this month', 'to_modifier' => 'last day of this month'],
    ];

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fromField = $options['from_field'];
        $toField = $options['to_field'];
        $ranges = $this->buildRanges($options);

        $dateOptions = [
            'widget' => 'single_text',
            'html5' => false,
            'input' => $options['input'],
            'model_timezone' => $options['model_timezone'],
            'view_timezone' => $options['view_timezone'],
        ];

        $builder
            ->add($fromField, DateType::class, $dateOptions)
            ->add($toField, DateType::class, $dateOptions)
            ->add('range', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => $options['label'],
                'attr' => array_merge(
                    ['class' => 'shorcuts_date', 'autocomplete' => 'off'],
                    $options['single_date_picker'] ? ['data-single-date' => 'true'] : []
                ),
            ]);

        $this->addPostSetDataListener($builder, $fromField, $toField, $ranges);
        $this->addPreSubmitListener($builder, $fromField, $toField, $ranges);
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $fromField = $options['from_field'];
        $toField = $options['to_field'];
        $ranges = $this->buildRanges($options);

        // Make date fields render as hidden inputs
        $view->children[$fromField]->vars['type'] = 'hidden';
        $view->children[$toField]->vars['type'] = 'hidden';

        // Inject JS data attributes for the date picker
        $view->children['range']->vars['attr']['data-from'] = $view->children[$fromField]->vars['id'];
        $view->children['range']->vars['attr']['data-to'] = $view->children[$toField]->vars['id'];
        $view->children['range']->vars['attr']['data-ranges'] = json_encode($ranges, \JSON_THROW_ON_ERROR);

        // Pass field names to template for rendering
        $view->vars['date_range_from_field'] = $fromField;
        $view->vars['date_range_to_field'] = $toField;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'from_field' => 'from',
            'to_field' => 'to',
            'label' => 'Quand ?',
            'ranges' => 'preset', // 'preset', 'none', or custom array
            'single_date_picker' => false,
            // DateType options (passed through to child fields)
            'input' => 'datetime',
            'model_timezone' => null,
            'view_timezone' => null,
        ]);

        $resolver->setAllowedTypes('from_field', 'string');
        $resolver->setAllowedTypes('to_field', 'string');
        $resolver->setAllowedTypes('label', ['string', 'null']);
        $resolver->setAllowedTypes('ranges', ['string', 'array']);
        $resolver->setAllowedTypes('single_date_picker', 'bool');
        $resolver->setAllowedTypes('input', 'string');
        $resolver->setAllowedTypes('model_timezone', ['string', 'null']);
        $resolver->setAllowedTypes('view_timezone', ['string', 'null']);

        $resolver->setAllowedValues('ranges', static fn (mixed $value): bool => \is_array($value) || \in_array($value, ['preset', 'none'], true));
        $resolver->setAllowedValues('input', ['datetime', 'datetime_immutable', 'string', 'timestamp', 'array']);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'date_range';
    }

    /**
     * Build the ranges array based on options.
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    private function buildRanges(array $options): array
    {
        if ('none' === $options['ranges'] || $options['single_date_picker']) {
            return [];
        }

        if ('preset' === $options['ranges']) {
            $ranges = [];
            foreach (self::PRESET_RANGES as $label => $config) {
                $from = new DateTime($config['modifier']);
                $to = null !== $config['to_modifier'] ? new DateTime($config['to_modifier']) : null;
                $ranges[$label] = [$from->format('Y-m-d'), $to?->format('Y-m-d')];
            }

            return $ranges;
        }

        return $options['ranges'];
    }

    /**
     * Add listener to populate range label from existing dates.
     */
    private function addPostSetDataListener(
        FormBuilderInterface $builder,
        string $fromField,
        string $toField,
        array $ranges,
    ): void {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($ranges, $fromField, $toField): void {
            $form = $event->getForm();
            $rangeField = $form->get('range');

            /** @var DateTimeInterface|null $from */
            $from = $form->get($fromField)->getData();

            /** @var DateTimeInterface|null $to */
            $to = $form->get($toField)->getData();

            if (null === $from) {
                return;
            }

            $label = $this->findRangeLabel($from, $to, $ranges);
            $rangeField->setData($label);
        });
    }

    /**
     * Add listener to populate range label from submitted dates.
     */
    private function addPreSubmitListener(
        FormBuilderInterface $builder,
        string $fromField,
        string $toField,
        array $ranges,
    ): void {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($ranges, $fromField, $toField): void {
            $data = $event->getData();

            $from = ($data[$fromField] ?? null) ?: null;
            $to = ($data[$toField] ?? null) ?: null;

            if (null === $from) {
                return;
            }

            $fromDate = new DateTime($from);
            $toDate = null !== $to ? new DateTime($to) : null;

            $data['range'] = $this->findRangeLabel($fromDate, $toDate, $ranges);
            $event->setData($data);
        });
    }

    /**
     * Find the label for a date range, or generate a custom label.
     */
    private function findRangeLabel(?DateTimeInterface $from, ?DateTimeInterface $to, array $ranges): string
    {
        if (null === $from) {
            return '';
        }

        $fromStr = $from->format('Y-m-d');
        $toStr = $to?->format('Y-m-d');

        // Check predefined ranges
        foreach ($ranges as $label => $range) {
            if ($range[0] === $fromStr && $range[1] === $toStr) {
                return $label;
            }
        }

        // Generate custom date label
        return $this->formatCustomDateLabel($from, $to);
    }

    private function formatCustomDateLabel(DateTimeInterface $from, ?DateTimeInterface $to): string
    {
        if (null === $to) {
            return \sprintf('A partir du %s', $this->formatDate($from));
        }

        if ($to->format('Y-m-d') === $from->format('Y-m-d')) {
            return \sprintf('Le %s', $this->formatDate($from));
        }

        return \sprintf('Du %s au %s', $this->formatDate($from), $this->formatDate($to));
    }

    private function formatDate(DateTimeInterface $date): string
    {
        $formatter = IntlDateFormatter::create(
            null,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE
        );

        return $formatter->format($date->getTimestamp());
    }
}
