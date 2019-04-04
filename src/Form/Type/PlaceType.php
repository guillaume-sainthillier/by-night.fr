<?php

namespace App\Form\Type;

use App\Entity\Country;
use App\Entity\Place;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => true,
                'label' => 'Où ça ?',
                'attr' => [
                    'placeholder' => 'Indiquez le nom du lieu',
                ],
            ])
            ->add('rue', TextType::class, [
                'required' => false,
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
            ])
            ->add('codePostal', TextType::class, [
                'required' => false,
            ])
            ->add('country', EntityType::class, [
                'label' => 'Pays',
                'placeholder' => '?',
                'class' => Country::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC');
                },
                'choice_label' => 'name',
            ]);

        $builder->get('latitude')->addModelTransformer(new CallbackTransformer(
            function ($latitude) {
                return (float)$latitude ?: null;
            },
            function ($latitude) {
                return (float)$latitude ?: null;
            }
        ));

        $builder->get('longitude')->addModelTransformer(new CallbackTransformer(
            function ($latitude) {
                return (float)$latitude ?: null;
            },
            function ($latitude) {
                return (float)$latitude ?: null;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Place::class,
        ]);
    }

    public function getName()
    {
        return 'app_place';
    }
}
