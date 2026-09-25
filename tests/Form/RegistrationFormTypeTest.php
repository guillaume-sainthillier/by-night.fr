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
 * A new account asks for a password of 6 characters or more, as a password reset does.
 */
final class RegistrationFormTypeTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providePasswords(): iterable
    {
        yield 'one character' => ['a', false];
        yield 'five characters' => ['abcde', false];
        yield 'six characters' => ['abcdef', true];
    }

    #[DataProvider('providePasswords')]
    public function testThePasswordHasSixCharactersOrMore(string $password, bool $accepted): void
    {
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(RegistrationFormType::class, new User(), ['csrf_protection' => false]);

        $form->submit(['plainPassword' => ['first' => $password, 'second' => $password]], false);

        self::assertSame($accepted, 0 === $form->get('plainPassword')->getErrors(true)->count());
    }
}
