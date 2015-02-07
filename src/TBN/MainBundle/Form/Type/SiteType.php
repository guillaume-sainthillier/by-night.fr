<?php

namespace TBN\MainBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('subdomain','text',[
                "label" => "Sous Domaine"
            ])
            ->add('nom','text',[
                "label" => "Nom de la ville"
            ])
            ->add('adjectifSingulier','text',[
                "label" => "Adjectif singulier"
            ])
            ->add('adjectifPluriel','text',[
                "label" => "Adjectif pluriel"
            ])
            ->add('description','textarea',[
                "label" => "Description"
            ])
            ->add('facebookIdPage','text',[
                "label" => "N° Page FaceBook",
                "required"  => false
            ])
            ->add('googleIdPage','text',[
                "label" => "N° Page Google+",
                "required"  => false
            ])
            ->add('twitterIdPage','text',[
                "label" => "N° Page Twitter",
                "required"  => false
            ])
            ->add('twitterURLWidget','text',[
                "label" => "URL du Widget Twitter",
                "required"  => false
            ])
            ->add('twitterIdWidget','text',[
                "label" => "ID du Widget Twitter",
                "required"  => false
            ])
            ->add('distanceMax','number',[
                "label" => "Distance Max",
                "required"  => false
            ])
            ->add('latitude','number',[
                "label" => "Latitude",
                "required"  => false
            ])
            ->add('longitude','number',[
                "label" => "Longitude",
                "required"  => false
            ])
            ->add('isActif','checkbox',[
                "label" => "Actif",
                "required" => false
            ])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
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
