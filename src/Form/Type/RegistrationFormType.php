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
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class RegistrationFormType extends AbstractType
{
    /**
     * Builds the embedded form representing the user.
     *
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Public (profile URL, comments, search of members); the entity keeps it unique
            ->add('username', TextType::class, [
                'label' => "Nom d'utilisateur",
                'help' => 'Visible sur votre profil et vos commentaires.',
                'constraints' => [
                    new NotBlank(message: "Veuillez choisir un nom d'utilisateur."),
                    new Length(min: 2, minMessage: "Votre nom d'utilisateur doit comporter au moins {{ limit }} caractères."),
                ],
                'attr' => ['autocomplete' => 'username'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                    new Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                ],
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(message: 'Veuillez saisir votre prénom.')],
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Veuillez saisir votre nom.')],
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('plainPassword', NewPasswordType::class)
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(),
                'action_name' => 'register',
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
