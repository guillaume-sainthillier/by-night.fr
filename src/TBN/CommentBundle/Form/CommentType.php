<?php

namespace TBN\CommentBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CommentType extends AbstractType
{
        /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('commentaire','textarea', [
                "label" => false,
                "attr" => [
                    "placeholder" => "Laissez un message",
                    "rows" => 4
                ]
            ])
        ;
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\CommentBundle\Entity\Comment'
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'tbn_commentbundle_comment';
    }
}
