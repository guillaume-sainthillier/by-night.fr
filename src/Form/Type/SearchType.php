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

        if (empty($data['range'])) {
            $data['range'] = 25;
        }

        $event->setData($data);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('du', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
            ])
            ->add('au', DateType::class, [
                'required' => false,
                'label' => 'Au',
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
            ])
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
        ;
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
