<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShortcutType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['from', 'to', 'ranges']);
        $resolver->setAllowedTypes('from', ['string']);
        $resolver->setAllowedTypes('to', ['string']);
        $resolver->setAllowedTypes('ranges', ['array']);

        $resolver->setDefaults([
            'attr' => ['class' => 'shorcuts_date', 'autocomplete' => 'off'],
            'required' => false,
            'mapped' => false,
            'from' => null,
            'to' => null,
            'ranges' => [],
        ]);
    }

    public function getParent()
    {
        return TextType::class;
    }
}
