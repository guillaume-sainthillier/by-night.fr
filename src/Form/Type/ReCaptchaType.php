<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Form\EventListener\ReCaptchaListener;
use App\Validator\Constraints\ReCaptchaResponse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReCaptchaType extends AbstractType
{
    private string $siteKey;
    private ReCaptchaListener $reCaptchaListener;

    public function __construct(string $siteKey, ReCaptchaListener $reCaptchaListener)
    {
        $this->siteKey = $siteKey;
        $this->reCaptchaListener = $reCaptchaListener;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber($this->reCaptchaListener);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'block_prefix' => 'recaptcha',
            'block_name' => 'g-recaptcha-response',
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
}
