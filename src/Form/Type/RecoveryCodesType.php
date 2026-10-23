<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * The confirmation that the recovery codes are kept somewhere safe: only then are they saved
 */
final class RecoveryCodesType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('saved', CheckboxType::class, [
            'label' => 'J\'ai conservé ces codes en lieu sûr',
            'constraints' => [new IsTrue(message: 'Conservez vos codes avant de continuer.')],
        ]);
    }
}
