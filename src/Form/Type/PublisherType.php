<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Entity\Publisher;
use App\Picture\ImageFormats;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * The page of a publisher, as the members who manage it edit it in their personal space.
 */
final class PublisherType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'help' => "Le nom affiché sur votre page et sur vos événements. L'adresse de votre page ne change pas.",
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Présentation',
                'attr' => [
                    'rows' => 6,
                    'maxlength' => Publisher::DESCRIPTION_MAX_LENGTH,
                    'placeholder' => 'Votre lieu, votre collectif, ce que vous programmez…',
                ],
            ])
            ->add('website', UrlType::class, [
                'required' => false,
                'label' => 'Site web',
                'default_protocol' => 'https',
                'icon-prepend' => 'lucide:globe',
                'attr' => ['placeholder' => 'https://…'],
            ])
            ->add('socialLinks', PublisherSocialLinksType::class)
            ->add('logoFile', VichImageType::class, [
                'required' => false,
                'label' => 'Logo',
                'attr' => ['data-max-size' => ImageFormats::MAX_SIZE],
                'delete_label' => 'Supprimer le logo',
                'layout' => 'horizontal',
                'help' => "Une image carrée (ex.\u{a0}: 512\u{a0}×\u{a0}512 pixels).",
                'thumb_params' => ['height' => 200],
            ])
            ->add('coverFile', VichImageType::class, [
                'required' => false,
                'label' => 'Image de couverture',
                'attr' => ['data-max-size' => ImageFormats::MAX_SIZE],
                'delete_label' => "Supprimer l'image de couverture",
                'layout' => 'horizontal',
                'help' => "Une image large (ex.\u{a0}: 1600\u{a0}×\u{a0}500 pixels).",
                'thumb_params' => ['height' => 200],
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Publisher::class,
        ]);
    }
}
