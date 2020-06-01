<?php

/*
 * This file is part of DocImmo.
 * Copyright (c) Cimalis - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Guillaume Sainthillier <guillaume@silarhi.fr>
 */

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class CollectionEntryExtension extends AbstractTypeExtension
{
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['confirm_delete_entry_label'] = $options['confirm_delete_entry_label'];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'confirm_delete_entry_label' => null,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        yield TextType::class;
        yield ChoiceType::class;
        yield FileType::class;
        yield VichFileType::class;
    }
}
