<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A new passkey: its name, and the browser's answer to the registration options (assets/js/listeners/webauthn.js
 * fills it before submitting)
 */
final class PasskeyRegistrationType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la clé',
                'attr' => ['maxlength' => 100, 'placeholder' => 'iPhone, YubiKey, ordinateur du bureau…'],
                'constraints' => [new NotBlank(), new Length(max: 100)],
            ])
            ->add('credential', HiddenType::class, [
                'attr' => ['data-webauthn-response' => true],
                'constraints' => [new NotBlank(message: 'Votre navigateur n\'a pas créé de clé d\'accès : réessayez.')],
            ]);
    }
}
