<?php

namespace TBN\UserBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use FOS\UserBundle\Form\Type\RegistrationFormType as BaseType;

class RegistrationFormType extends BaseType
{

    /**
     * Builds the embedded form representing the user.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    protected function buildUserForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildUserForm($builder,$options);

        $builder
            ->add('username', null, ['disabled' => true, 'label' => 'form.username', 'translation_domain' => 'FOSUserBundle'])
            ->add('firstName', null, ['required' => false])
        ;
    }

    public function getName()
    {
        return 'tbn_user_registration';
    }
}