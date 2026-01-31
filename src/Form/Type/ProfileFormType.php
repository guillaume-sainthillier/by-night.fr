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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

final class ProfileFormType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => "Nom d'utilisateur",
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
            ])
            ->add('firstname', TextType::class, ['required' => false, 'label' => 'Prénom'])
            ->add('lastname', TextType::class, ['required' => false, 'label' => 'Nom'])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Écrivez une courte description',
                ],
            ])
            ->add('website', UrlType::class, [
                'required' => false,
                'label' => 'Site Web',
            ])
            ->add('showSocials', CheckboxType::class, [
                'required' => false,
                'label' => 'Afficher un lien vers mes réseaux sociaux',
                'label_attr' => [
                    'class' => 'checkbox-switch',
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'label' => 'Photo de profil',
                'delete_label' => "Supprimer l'image de profil",
                'layout' => 'horizontal',
                'thumb_params' => [
                    'height' => 200,
                ],
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
