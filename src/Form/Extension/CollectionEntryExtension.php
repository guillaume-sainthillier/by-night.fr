<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CollectionEntryExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [
            TextType::class,
            ChoiceType::class,
        ];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['confirm_delete_entry_label'] = $options['confirm_delete_entry_label'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'confirm_delete_entry_label' => null,
        ]);
    }
}
