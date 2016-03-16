<?php

namespace TBN\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('nom', TextType::class, [
                "required" => true,
                "label" => "Où ça ?",
                "attr" => [
                    "placeholder" => "Indiquez le nom du lieu"
                ]
            ])
            ->add('rue', TextType::class, [
                "required" => false,
            ])
            ->add('latitude', HiddenType::class, [
                "required" => false,
            ])
            ->add('longitude', HiddenType::class, [
                "required" => false,
            ])
            ->add('ville', TextType::class, [
                "label" => "Ville"
            ])
            ->add('codePostal', TextType::class, [
                "required" => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Entity\Place'
        ]);
    }

    public function getName()
    {
        return 'tbn_place';
    }
}
