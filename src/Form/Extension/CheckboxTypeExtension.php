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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckboxTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritDoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [CheckboxType::class];
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label_attr' => ['class' => 'checkbox-custom'],
        ]);
    }
}
