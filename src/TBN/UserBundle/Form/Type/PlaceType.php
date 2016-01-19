<?php

namespace TBN\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('nom', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                "required" => true,
                "label" => "Où ça ?",
                "attr" => [
                    "placeholder" => "Indiquez le nom du lieu"
                ]
            ])
           ->add('rue',\Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                "required" => false,
            ])
            ->add('latitude',SymfonyComponentFormExtensionCoreTypeHiddenType, [
                "required" => false,
            ])
            ->add('longitude',SymfonyComponentFormExtensionCoreTypeHiddenType, [
                "required" => false,
            ])
            ->add('ville', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                "label" => "Ville"
            ])
            ->add('codePostal',\Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                "required" => false,
            ])
        ;
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
