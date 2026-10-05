<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A member closes their account (UserRemover): their events go with it or stay online, and they type a word to
 * confirm it.
 */
final class DeleteAccountFormType extends AbstractType
{
    /** The word the member types to confirm the deletion of their account */
    public const string CONFIRMATION = 'SUPPRIMER';

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $confirmationMessage = \sprintf('Tapez %s pour confirmer la suppression de votre compte.', self::CONFIRMATION);

        $builder
            ->add('delete_events', CheckboxType::class, [
                'required' => false,
            ])
            ->add('confirmation', TextType::class, [
                'constraints' => [
                    new NotBlank(message: $confirmationMessage),
                    new EqualTo(value: self::CONFIRMATION, message: $confirmationMessage),
                ],
            ]);
    }
}
