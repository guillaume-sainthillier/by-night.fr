<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Validator\Constraints\PasswordRequirements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A new password typed twice (sign-up, reset, profile): the first field carries the PasswordRequirements and the
 * strength meter (form theme block "new_password_row"), a mismatch is reported under the second one.
 */
final class NewPasswordType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['password_rules'] = PasswordRequirements::RULES;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            // Read and hashed by the controller, never set on the user as is
            'mapped' => false,
            'options' => [
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ],
            'second_options' => [
                'label' => 'Confirmation du mot de passe',
            ],
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            // The mismatch is found on the pair: show it under the confirmation, where the member typed it
            'error_mapping' => ['.' => 'second'],
        ]);

        // Merged rather than a default, so that a form that renames the field keeps the rules
        $resolver->setNormalizer('first_options', static fn (Options $options, array $value): array => [
            'label' => 'Mot de passe',
            ...$value,
            'constraints' => [new PasswordRequirements()],
        ]);
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'new_password';
    }
}
