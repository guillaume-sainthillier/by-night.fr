<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $constraintsOptions = [
            'message' => 'Le mot de passe actuel est incorrect.',
        ];

        if (!empty($options['validation_groups'])) {
            $constraintsOptions['groups'] = [reset($options['validation_groups'])];
        }

        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'Mot de passe actuel',
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
                new UserPassword($constraintsOptions),
            ],
            'attr' => [
                'autocomplete' => 'current-password',
            ],
        ]);

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'options' => [
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ],
            'first_options' => ['label' => 'Mot de passe'],
            'second_options' => ['label' => 'Répeter le mot de passe'],
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
