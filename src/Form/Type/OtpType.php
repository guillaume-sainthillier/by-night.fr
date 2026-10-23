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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A one-time code in the OTP input of Tabler (templates/components/OtpInput.html.twig): one slot per digit drawn over
 * a single real input, so password managers and the SMS / authenticator autofill still see one field.
 */
final class OtpType extends AbstractType
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'length' => 6,
            // Submits the form once the last digit is typed
            'autosubmit' => true,
            'trim' => true,
        ]);
        $resolver->setAllowedTypes('length', 'int');
        $resolver->setAllowedTypes('autosubmit', 'bool');
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['length'] = $options['length'];
        $view->vars['autosubmit'] = $options['autosubmit'];
    }

    #[Override]
    public function getParent(): string
    {
        return TextType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'otp';
    }
}
