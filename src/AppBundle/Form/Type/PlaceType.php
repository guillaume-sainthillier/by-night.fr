<?php

namespace TBN\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;


use Symfony\Component\OptionsResolver\OptionsResolver;
use TBN\AgendaBundle\Entity\Place;

class PlaceType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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
            ])
        ;

        $builder->get('latitude')->addModelTransformer(new CallbackTransformer(
            function ($latitude) {
                return floatval($latitude) ?: null;
            },
            function ($latitude) {
                return floatval($latitude) ?: null;
            }
        ));

        $builder->get('longitude')->addModelTransformer(new CallbackTransformer(
            function ($latitude) {
                return floatval($latitude) ?: null;
            },
            function ($latitude) {
                return floatval($latitude) ?: null;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Place::class
        ]);
    }

    public function getName()
    {
        return 'tbn_place';
    }
}
