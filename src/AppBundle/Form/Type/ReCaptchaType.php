<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 22:19
 */

namespace AppBundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use AppBundle\Validator\Constraints\ReCaptchaResponse;


class ReCaptchaType extends AbstractType
{
    private $sitekey;

    public function __construct($sitekey)
    {
        $this->sitekey = $sitekey;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'mapped' => false,
            'compound' => false,
            'attr' => array(
                'data-sitekey' => $this->sitekey,
                'class' => 'g-recaptcha'
            ),
            'constraints' => array(
                new ReCaptchaResponse(),
                new NotBlank()
            )
        ));
    }

    public function getBlockPrefix()
    {
        return 'recaptcha';
    }
}
