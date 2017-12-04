<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 22:19.
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use App\Validator\Constraints\ReCaptchaResponse;

class ReCaptchaType extends AbstractType
{
    /**
     * @var string
     */
    private $siteKey;

    public function __construct(string $siteKey)
    {
        $this->siteKey = $siteKey;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'mapped'   => false,
            'compound' => false,
            'attr'     => array(
                'data-sitekey' => $this->siteKey,
                'class'        => 'g-recaptcha',
            ),
            'constraints' => array(
                new ReCaptchaResponse(),
                new NotBlank(),
            ),
        ));
    }

    public function getBlockPrefix()
    {
        return 'recaptcha';
    }
}
