<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Validator\Constraints\TotpCode;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The first code of the authenticator app just scanned: it turns the TOTP second factor on
 */
final class TotpActivationType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', OtpType::class, [
            'label' => 'Code affiché par l\'application',
            'constraints' => [new NotBlank(), new TotpCode()],
        ]);
    }
}
