<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TBN\UserBundle\Form\Type;

use FOS\UserBundle\Form\Type\ProfileFormType as BaseType;
use Symfony\Component\Form\FormBuilderInterface;

class ProfileFormType extends BaseType
{

    public function getName()
    {
        return 'tbn_user_profile';
    }

    /**
     * Builds the embedded form representing the user.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    protected function buildUserForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', null, [
                'label' => 'form.username',
                'translation_domain' => 'FOSUserBundle'
            ])
            ->add('email', 'email', ['label' => 'form.email', 'translation_domain' => 'FOSUserBundle'])
            ->add('description', 'textarea', [
                'label' => 'Description',
                "attr" => [
                    "placeholder" => "Ecrivez une courte description"
                ]
            ])
        ;
    }
}
