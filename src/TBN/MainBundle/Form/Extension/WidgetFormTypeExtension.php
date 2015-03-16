<?php

namespace TBN\MainBundle\Form\Extension;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;

/**
 * 
 * 
 * @author Guillaume Sainthillier
 */
class WidgetFormTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        
        $view->vars['group_class']              = $options['group_class'];
        $view->vars['base_class']               = $options['base_class'];
        $view->vars['widget_class']             = $options['widget_class'];
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'group_class'               => 'form-group',
            'base_class'                => 'form-control',
            'widget_class'              => null,            
            'label_attr'                => array('class' => 'control-label')
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
