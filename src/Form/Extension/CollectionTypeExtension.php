<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionTypeExtension extends AbstractTypeExtension
{
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        if (!isset($view->vars['attr'])) {
            $view->vars['attr'] = [];
        }

        $view->vars['attr']['data-prototype-name'] = $options['prototype_name'];
        $view->vars['prototype_name'] = $options['prototype_name'];
        $view->vars['add_entry_label'] = $options['add_entry_label'];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'entry_options' => [
                'label' => false,
                //  'block_prefix' => 'app_collection_entry',
            ],
            'add_entry_label' => null,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'block_prefix' => 'app_collection',
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        yield CollectionType::class;
    }
}
