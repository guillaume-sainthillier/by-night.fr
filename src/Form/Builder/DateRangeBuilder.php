<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Builder;

use App\Form\Type\HiddenDateType;
use App\Form\Type\ShortcutType;
use DateTime;
use DateTimeInterface;
use IntlDateFormatter;
use const JSON_THROW_ON_ERROR;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class DateRangeBuilder
{
    public function finishView(FormView $view, FormInterface $form): void
    {
        $fromName = $form->get('shortcut')->getConfig()->getOption('from');
        $toName = $form->get('shortcut')->getConfig()->getOption('to');

        $view->children['shortcut']->vars['attr']['data-from'] = $view->children[$fromName]->vars['id'];
        $view->children['shortcut']->vars['attr']['data-to'] = $view->children[$toName]->vars['id'];
        $view->children['shortcut']->vars['attr']['data-ranges'] = json_encode($form->get('shortcut')->getConfig()->getOption('ranges'), JSON_THROW_ON_ERROR);
    }

    public function addShortcutDateFields(FormBuilderInterface $builder, string $fromName, string $toName): void
    {
        $ranges = [
            'N\'importe quand' => [(new DateTime('now'))->format('Y-m-d'), null],
            'Aujourd\'hui' => [(new DateTime('now'))->format('Y-m-d'), (new DateTime('now'))->format('Y-m-d')],
            'Demain' => [(new DateTime('tomorrow'))->format('Y-m-d'), (new DateTime('tomorrow'))->format('Y-m-d')],
            'Ce week-end' => [(new DateTime('friday this week'))->format('Y-m-d'), (new DateTime('sunday this week'))->format('Y-m-d')],
            'Cette semaine' => [(new DateTime('monday this week'))->format('Y-m-d'), (new DateTime('sunday this week'))->format('Y-m-d')],
            'Ce mois' => [(new DateTime('first day of this month'))->format('Y-m-d'), (new DateTime('last day of this month'))->format('Y-m-d')],
        ];
        $this->addDateFields($builder, $fromName, $toName, $ranges);
    }

    public function addDateFields(FormBuilderInterface $builder, string $fromName, string $toName, array $ranges = []): void
    {
        $builder
            ->add($fromName, HiddenDateType::class)
            ->add($toName, HiddenDateType::class)
            ->add('shortcut', ShortcutType::class, [
                'from' => $fromName,
                'to' => $toName,
                'ranges' => $ranges,
            ])
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($ranges) {
                $form = $event->getForm();
                $shortcut = $form->get('shortcut');
                $fromName = $shortcut->getConfig()->getOption('from');
                $toName = $shortcut->getConfig()->getOption('to');

                /** @var DateTimeInterface $from */
                $from = $form->get($fromName)->getData();

                /** @var DateTimeInterface|null $to */
                $to = $form->get($toName)->getData();

                if (null === $from) {
                    return;
                }

                $from = $from->format('Y-m-d');
                if (null !== $to) {
                    $to = $to->format('Y-m-d');
                }

                foreach ($ranges as $label => $range) {
                    if ($range[0] === $from && $range[1] === $to) {
                        $shortcut->setData($label);

                        return;
                    }
                }

                //Custom dates
                $from = new DateTime($from);
                if (null !== $to) {
                    $to = new DateTime($to);
                }

                if (null === $to) {
                    $label = sprintf('A partir du %s', $this->formatDate($from));
                } elseif ($to->getTimestamp() === $from->getTimestamp()) {
                    $label = sprintf('Le %s', $this->formatDate($from));
                } else {
                    $label = sprintf('Du %s au %s', $this->formatDate($from), $this->formatDate($to));
                }
                $shortcut->setData($label);
            })
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($ranges) {
                $data = $event->getData();
                $form = $event->getForm();
                $shortcut = $form->get('shortcut');
                $fromName = $shortcut->getConfig()->getOption('from');
                $toName = $shortcut->getConfig()->getOption('to');

                $from = ($data[$fromName] ?? null) ?: null;
                $to = ($data[$toName] ?? null) ?: null;

                if (null === $from) {
                    return;
                }

                foreach ($ranges as $label => $range) {
                    if ($range[0] === $from && $range[1] === $to) {
                        $data['shortcut'] = $label;
                        $event->setData($data);

                        return;
                    }
                }

                //Custom dates
                $from = new DateTime($from);
                if (null !== $to) {
                    $to = new DateTime($to);
                }

                if (null === $to) {
                    $label = sprintf('A partir du %s', $this->formatDate($from));
                } elseif ($to->getTimestamp() === $from->getTimestamp()) {
                    $label = sprintf('Le %s', $this->formatDate($from));
                } else {
                    $label = sprintf('Du %s au %s', $this->formatDate($from), $this->formatDate($to));
                }
                $data['shortcut'] = $label;
                $event->setData($data);
            });
    }

    /**
     * @return false|string
     */
    private function formatDate(DateTimeInterface $date)
    {
        $formatter = IntlDateFormatter::create(
            null,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE);

        return $formatter->format($date->getTimestamp());
    }
}
