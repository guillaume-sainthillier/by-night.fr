<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;


use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Entity\Place;

class CityAutocompleteType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                "required" => true,
                "label" => false,
                "attr" => [
                    "placeholder" => "Entrez un nom de ville...",
                    "class" => "city-picker"
                ]
            ])
            ->add('city', HiddenType::class, [
                "label" => false,
                "attr" => [
                    "class" => "city-value"
                ],
                "required" => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "csrf_protection" => false
        ]);
    }

    public function getName()
    {
        return 'app_city_picker';
    }
}
