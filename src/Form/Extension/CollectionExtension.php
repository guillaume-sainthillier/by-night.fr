<?php

namespace App\Form\Extension;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

/**
 * @author Guillaume Sainthillier
 */
class CollectionExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($form->getConfig()->hasAttribute('prototype')) {
            $view->vars['prototype'] = $form->getConfig()->getAttribute('prototype')->createView($view);
        }

        $view->vars['group_class']  = $options['group_class'];
        $view->vars['base_class']   = $options['base_class'];
        $view->vars['widget_class'] = $options['widget_class'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'allow_add'      => true,
            'group_class'    => null,
            'allow_delete'   => true,
            'prototype'      => true,
            'prototype_name' => '__name__',
            'widget_class'   => 'widget_collection',
            'base_class'     => null,
            'by_reference'   => false, //GARANTIE D'APPEL de addXXX sur l'objet parent de la collection
        ));

        $resolver->setNormalizer('block_name', function (Options $options, $value) {
            return $value;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return CollectionType::class;
    }
}
