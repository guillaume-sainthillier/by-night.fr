<?php

namespace TBN\MainBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


/**
 * Classe qui définit le type ccmm_datepicker utilisé dans la création de formulaires
 * Cette classe s'applique pour les input de type text, puis est utilisée
 * par le plugin datePicker de jQuery UI
 * 
 * @author Guillaume Sainthillier
 */
class DateTypeExtension extends AbstractTypeExtension
{

    /**
     * {@inheritDoc}
     */     
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {        
        $resolver->setDefaults(array(
            'input'  => 'datetime',
            'widget' => 'single_text',
            'format' => 'dd/MM/yyyy',
            'widget_class' => 'widget_datepicker'
        ));
    }
    
    /**
     * {@inheritDoc}
     */
    public function getExtendedType()
    {
        return 'date';
    }
}

