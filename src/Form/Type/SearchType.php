<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Form\Builder\DateRangeBuilder;
use App\Search\SearchEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchType extends AbstractType
{
    private DateRangeBuilder $dateRangeBuilder;

    public function __construct(DateRangeBuilder $dateRangeBuilder)
    {
        $this->dateRangeBuilder = $dateRangeBuilder;
    }

    public function onPreSubmit(FormEvent $event)
    {
        $data = $event->getData();
        if (empty($data['range'])) {
            $data['range'] = 25;
        }

        $event->setData($data);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $this->dateRangeBuilder->finishView($view, $form);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->dateRangeBuilder->addShortcutDateFields($builder, 'from', 'to');
        $builder
            ->add('range', NumberType::class, [
                'label' => 'Rayon (KM)',
                'attr' => ['placeholder' => 'Quand quel rayon cherchez-vous ?'],
            ])
            ->add('type_manifestation', ChoiceType::class, [
                'choices' => $options['types_manif'],
                'label' => 'Quoi ?',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['title' => 'Tous', 'data-live-search' => true], ])
            ->add('term', TextType::class, [
                'required' => false,
                'label' => 'Mot-clés',
                'attr' => ['placeholder' => 'Quel événement cherchez-vous ?'], ])
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                [$this, 'onPreSubmit']
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
            'types_manif' => [],
            'data_class' => SearchEvent::class,
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
