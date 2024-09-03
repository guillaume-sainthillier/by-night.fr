<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use Generator;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FormTypeExtension extends AbstractTypeExtension
{
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['icon_prepend'] = $options['icon-prepend'];
        $view->vars['icon_append'] = $options['icon-append'];
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_domain' => false,
            'icon-prepend' => null,
            'icon-append' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-return Generator<int, FormType::class, mixed, void>
     */
    public static function getExtendedTypes(): iterable
    {
        yield FormType::class;
    }
}
