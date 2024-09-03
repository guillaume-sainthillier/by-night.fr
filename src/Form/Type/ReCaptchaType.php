<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\EventSubscriber\ReCaptchaSubscriber;
use App\Validator\Constraints\ReCaptchaResponse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ReCaptchaType extends AbstractType
{
    public function __construct(private readonly string $siteKey, private readonly ReCaptchaSubscriber $reCaptchaSubscriber)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber($this->reCaptchaSubscriber);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
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
