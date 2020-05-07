<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Validator\Constraints\ReCaptchaResponse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReCaptchaType extends AbstractType
{
    private string $siteKey;

    public function __construct(string $siteKey)
    {
        $this->siteKey = $siteKey;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'mapped' => false,
            'compound' => false,
            'attr' => [
                'data-sitekey' => $this->siteKey,
                'class' => 'g-recaptcha',
            ],
            'constraints' => [
                new ReCaptchaResponse(),
                new NotBlank(),
            ],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'recaptcha';
    }
}
