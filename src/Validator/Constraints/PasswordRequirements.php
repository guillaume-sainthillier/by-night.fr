<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * The rules of a new password (sign-up, reset, profile). The strength meter under the field checks the same
 * RULES as the member types (NewPasswordType hands them to the page), so both always agree.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class PasswordRequirements extends Compound
{
    /**
     * Each rule: a pattern both PCRE (with the "u" flag) and JavaScript (with the "u" flag) read the same way,
     * the label of the meter and the error message.
     *
     * @var array<string, array{pattern: string, label: string, message: string}>
     */
    public const array RULES = [
        'length' => [
            'pattern' => '.{8,}',
            'label' => '8 caractères',
            'message' => 'Votre mot de passe doit comporter au moins 8 caractères.',
        ],
        'digit' => [
            'pattern' => '\d',
            'label' => '1 chiffre',
            'message' => 'Votre mot de passe doit contenir au moins un chiffre.',
        ],
        'uppercase' => [
            'pattern' => '\p{Lu}',
            'label' => '1 majuscule',
            'message' => 'Votre mot de passe doit contenir au moins une majuscule.',
        ],
    ];

    protected function getConstraints(array $options): array
    {
        $constraints = [
            new NotBlank(message: 'Veuillez saisir un mot de passe.'),
            // Symfony's hashers refuse anything longer than 4096 characters
            new Length(max: 4096),
        ];

        foreach (self::RULES as $rule) {
            $constraints[] = new Regex(pattern: '/' . $rule['pattern'] . '/su', message: $rule['message']);
        }

        return $constraints;
    }
}
