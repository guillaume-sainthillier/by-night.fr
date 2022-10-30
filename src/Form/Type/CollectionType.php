<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType as BaseCollectionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionType extends AbstractType
{
    /**
     * @return void
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if (!isset($view->vars['attr'])) {
            $view->vars['attr'] = [];
        }

        foreach ($view as $entryView) {
            $entryView->vars['block_prefixes'] = $this->replaceCollectionEntryBlockPrefix($entryView->vars['block_prefixes']);
        }

        if ($form->getConfig()->getAttribute('prototype')) {
            $view->vars['prototype']->vars['block_prefixes'] = $this->replaceCollectionEntryBlockPrefix($view->vars['prototype']->vars['block_prefixes']);
        }

        $view->vars['attr']['data-prototype-name'] = $options['prototype_name'];
        $view->vars['collection_item_attr'] = $options['collection_item_attr'];
        $view->vars['prototype_name'] = $options['prototype_name'];
        $view->vars['add_entry_label'] = $options['add_entry_label'];
        $view->vars['delete_entry_confirm_label'] = $options['delete_entry_confirm_label'];
        $view->vars['end_entry_template'] = $options['end_entry_template'];
    }

    private function replaceCollectionEntryBlockPrefix(array $prefixes): array
    {
        foreach ($prefixes as $i => $prefix) {
            if ('collection_entry' === $prefix) {
                array_splice($prefixes, $i + 1, 0, 'app_collection_entry');
            }
        }

        return $prefixes;
    }

    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => false,
            'entry_options' => [
                'label' => false,
            ],
            'collection_item_attr' => [],
            'add_entry_label' => null,
            'delete_entry_confirm_label' => null,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'error_bubbling' => false,
            'end_entry_template' => null,
        ]);
    }

    public function getParent(): string
    {
        return BaseCollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'app_collection';
    }
}
