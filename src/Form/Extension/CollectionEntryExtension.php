<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use Generator;
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
    /**
     * @return void
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['confirm_delete_entry_label'] = $options['confirm_delete_entry_label'];
    }

    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'confirm_delete_entry_label' => null,
        ]);
    }

    /**
     * @psalm-return Generator<int, string, mixed, void>
     */
    public static function getExtendedTypes(): iterable
    {
        yield TextType::class;
        yield ChoiceType::class;
        yield FileType::class;
        yield VichFileType::class;
    }
}
