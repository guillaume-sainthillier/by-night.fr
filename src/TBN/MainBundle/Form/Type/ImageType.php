<?php

namespace TBN\MainBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', 'file', [
                'image_path' => 'webPath',
                'image_filter' => 'thumb_evenement',
                'data_class' => null,
                'label' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\MainBundle\Entity\Image'
        ]);
    }

    public function getName()
    {
        return 'tbn_image';
    }
}
