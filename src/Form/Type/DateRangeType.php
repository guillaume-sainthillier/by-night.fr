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
use DateTimeImmutable;
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
 * @phpstan-import-type DateRangePresetType from DateRangePreset
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
        $ranges = $this->buildRanges($options);

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
            'attr' => array_merge(
                ['class' => 'shorcuts_date', 'autocomplete' => 'off'],
                $options['single_date_picker'] ? ['data-single-date' => 'true'] : []
            ),
        ]);

        $this->addPreSubmitListener($builder, $ranges);
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $ranges = $this->buildRanges($options);

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

        $viewRanges = [];
        foreach ($ranges as $label => [$from, $to]) {
            $viewRanges[$label] = [
                $from->format('Y-m-d'),
                $to?->format('Y-m-d'),
            ];
        }
        $view->children['range']->vars['attr']['data-ranges'] = json_encode($viewRanges, \JSON_THROW_ON_ERROR);

        // Set initial range label from existing dates
        /** @var DateTimeInterface|null $from */
        $from = $form->get('from')->getData();
        /** @var DateTimeInterface|null $to */
        $to = $form->has('to') ? $form->get('to')->getData() : null;

        if (null !== $from) {
            $view->children['range']->vars['value'] = $this->findRangeLabel($from, $to, $ranges);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'from_field' => 'from',
            'to_field' => 'to',
            'label' => 'Quand ?',
            'ranges' => DateRangePreset::cases(), // DateRangePreset[], empty array for none
            'single_date_picker' => false,
            // DateType options (passed through to child fields)
            'input' => 'datetime',
            'model_timezone' => null,
            'view_timezone' => null,
        ]);

        $resolver->setAllowedTypes('from_field', 'string');
        $resolver->setAllowedTypes('to_field', ['string', 'null']);
        $resolver->setAllowedTypes('ranges', 'array');
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

    /**
     * Build the ranges array based on options.
     *
     * @return DateRangePresetType
     */
    private function buildRanges(array $options): array
    {
        if ([] === $options['ranges'] || $options['single_date_picker']) {
            return [];
        }

        return DateRangePreset::buildRanges($options['ranges']);
    }

    /**
     * @param DateRangePresetType $ranges
     *                                    Add listener to populate range label from submitted dates
     */
    private function addPreSubmitListener(
        FormBuilderInterface $builder,
        array $ranges,
    ): void {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($ranges): void {
            $data = $event->getData();

            $from = ($data['from'] ?? null) ?: null;
            $to = ($data['to'] ?? null) ?: null;

            if (null === $from) {
                return;
            }

            $fromDate = new DateTimeImmutable($from);
            $toDate = null !== $to ? new DateTimeImmutable($to) : null;

            $data['range'] = $this->findRangeLabel($fromDate, $toDate, $ranges);
            $event->setData($data);
        });
    }

    /**
     * @param DateRangePresetType $ranges
     *                                    Find the label for a date range, or generate a custom label
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
            [$rangeFrom, $rangeTo] = $range;
            $rangeFromStr = $rangeFrom->format('Y-m-d');
            $rangeToStr = $rangeTo?->format('Y-m-d');

            if ($rangeFromStr === $fromStr && $rangeToStr === $toStr) {
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
