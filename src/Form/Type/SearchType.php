<?php

namespace App\Form\Type;

use App\Search\SearchEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchType extends AbstractType
{

    public function onPreSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (isset($data['du']) && !$data['du']) {
            $data['du'] = \date('d/m/Y');
        }

        if (empty($data['page'])) {
            $data['page'] = 1;
        }

        if (empty($data['range'])) {
            $data['range'] = 25;
        }

        $event->setData($data);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('page', HiddenType::class)
            ->add('du', DateType::class, [
                'label' => 'Du',
                'label_attr' => ['class' => 'col-sm-6 control-label'],
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'attr' => ['data-date-format' => 'dd/mm/yyyy'],
            ])
            ->add('au', DateType::class, [
                'required' => false,
                'label' => 'Au',
                'label_attr' => ['class' => 'col-sm-3 control-label'],
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'attr' => ['data-date-format' => 'dd/mm/yyyy'],
            ])
            ->add('range', NumberType::class, [
                'label' => 'Rayon (KM)',
                'label_attr' => ['class' => 'col-sm-3 control-label'],
                'attr' => ['placeholder' => 'Quand quel rayon cherchez-vous ?'],
            ])
            ->add('type_manifestation', ChoiceType::class, [
                'choices' => $options['types_manif'],
                'label' => 'Quoi ?',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['title' => 'Tous', 'class' => 'form-control', 'data-style' => 'btn-primary btn-flat', 'data-live-search' => true], ])
            ->add('lieux', ChoiceType::class, [
                'choices' => $options['lieux'],
                'label' => 'Lieux',
                'label_attr' => ['class' => 'col-sm-3 control-label'],
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['title' => 'Tous', 'class' => 'form-control', 'data-style' => 'btn-primary btn-flat', 'data-live-search' => true], ])
            ->add('term', TextType::class, [
                'required' => false,
                'label' => 'Mot-clés',
                'label_attr' => ['class' => 'col-sm-3 control-label'],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Quel événement cherchez-vous ?'], ])
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                [$this, 'onPreSubmit']
            );
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'lieux' => [],
            'types_manif' => [],
            'data_class' => SearchEvent::class,
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'search';
    }
}
