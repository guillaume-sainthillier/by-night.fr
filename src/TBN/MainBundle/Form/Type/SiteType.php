<?php

namespace TBN\MainBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('subdomain', TextType::class, [
                'label' => 'Sous Domaine'
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom de la ville'
            ])
            ->add('adjectifSingulier', TextType::class, [
                'label' => 'Adjectif singulier'
            ])
            ->add('adjectifPluriel', TextType::class, [
                'label' => 'Adjectif pluriel'
            ])
            ->add('description', TextAreaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 6
                ]
            ])
            ->add('images', 'collection', [
                'type' => new ImageType,
                'required' => false
            ])
            ->add('facebookIdPage', TextType::class, [
                'label' => 'N° Page FaceBook',
                'required' => false
            ])
            ->add('googleIdPage', TextType::class, [
                'label' => 'N° Page Google+',
                'required' => false
            ])
            ->add('twitterIdPage', TextType::class, [
                'label' => 'N° Page Twitter',
                'required' => false
            ])
            ->add('twitterURLWidget', TextType::class, [
                'label' => 'URL du Widget Twitter',
                'required' => false
            ])
            ->add('twitterIdWidget', TextType::class, [
                'label' => 'ID du Widget Twitter',
                'required' => false
            ])
            ->add('distanceMax', 'number', [
                'label' => 'Distance Max',
                'required' => false
            ])
            ->add('latitude', 'number', [
                'label' => 'Latitude',
                'required' => false
            ])
            ->add('longitude', 'number', [
                'label' => 'Longitude',
                'required' => false
            ])
            ->add('isActif', 'checkbox', [
                'label' => 'Actif',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\MainBundle\Entity\Site'
        ]);
    }

    public function getName()
    {
        return 'tbn_administration_site';
    }
}
