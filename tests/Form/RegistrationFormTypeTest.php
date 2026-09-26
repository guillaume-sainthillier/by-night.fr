<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\Type\RegistrationFormType;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * A new member gives their first and last names, a username of 2 characters or more and a valid address; the
 * password rules are NewPasswordTypeTest's.
 */
final class RegistrationFormTypeTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideMissingFields(): iterable
    {
        yield 'no first name' => ['firstname', '', 'Veuillez saisir votre prénom.'];
        yield 'no last name' => ['lastname', '', 'Veuillez saisir votre nom.'];
        yield 'no username' => ['username', '', "Veuillez choisir un nom d'utilisateur."];
        yield 'a one-character username' => ['username', 'c', "Votre nom d'utilisateur doit comporter au moins 2 caractères."];
        yield 'no address' => ['email', '', 'Veuillez saisir votre adresse e-mail.'];
        yield 'not an address' => ['email', 'camille', 'Veuillez saisir une adresse e-mail valide.'];
    }

    #[DataProvider('provideMissingFields')]
    public function testTheFieldIsRequired(string $field, string $value, string $error): void
    {
        $data = [
            'firstname' => 'Camille',
            'lastname' => 'Martin',
            'username' => 'camille_m',
            'email' => 'camille@example.com',
            'plainPassword' => ['first' => 'Motdepasse1', 'second' => 'Motdepasse1'],
            $field => $value,
        ];
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(RegistrationFormType::class, new User(), ['csrf_protection' => false]);

        $form->submit($data);

        self::assertStringContainsString($error, (string) $form->get($field)->getErrors());
    }

    public function testACompleteFormIsValid(): void
    {
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(RegistrationFormType::class, new User(), ['csrf_protection' => false]);

        $form->submit([
            'firstname' => 'Camille',
            'lastname' => 'Martin',
            'username' => 'camille_m',
            'email' => 'camille@example.com',
            'plainPassword' => ['first' => 'Motdepasse1', 'second' => 'Motdepasse1'],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }
}
