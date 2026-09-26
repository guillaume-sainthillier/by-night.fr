<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Form;

use App\Form\Type\NewPasswordType;
use App\Tests\AppKernelTestCase;
use App\Validator\Constraints\PasswordRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * A new password (sign-up, reset, profile) has 8 characters, a digit and a capital, as the meter under the field says.
 */
final class NewPasswordTypeTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providePasswords(): iterable
    {
        yield 'empty' => ['', ['Veuillez saisir un mot de passe.']];
        yield 'seven characters' => ['Abcdef1', [PasswordRequirements::RULES['length']['message']]];
        yield 'no digit' => ['Abcdefgh', [PasswordRequirements::RULES['digit']['message']]];
        yield 'no capital' => ['abcdefg1', [PasswordRequirements::RULES['uppercase']['message']]];
        yield 'eight characters, a digit and a capital' => ['Abcdefg1', []];
        yield 'an accented capital' => ['élégancE1', []];
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('providePasswords')]
    public function testThePasswordMeetsTheRules(string $password, array $errors): void
    {
        $form = $this->submit($password, $password);

        self::assertSame($errors, $this->getErrors($form->get('password')->get('first')));
    }

    public function testAMismatchIsReportedUnderTheConfirmation(): void
    {
        $form = $this->submit('Abcdefg1', 'Abcdefg2');

        self::assertSame(['Les mots de passe ne correspondent pas.'], $this->getErrors($form->get('password')->get('second')));
        self::assertSame([], $this->getErrors($form->get('password')->get('first')));
    }

    public function testARenamedFieldKeepsTheRules(): void
    {
        $form = $this->submit('court', 'court', ['first_options' => ['label' => 'Nouveau mot de passe']]);

        self::assertSame('Nouveau mot de passe', $form->get('password')->get('first')->getConfig()->getOption('label'));
        self::assertNotSame([], $this->getErrors($form->get('password')->get('first')));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function submit(string $first, string $second, array $options = []): FormInterface
    {
        $form = self::getContainer()->get(FormFactoryInterface::class)
            ->createBuilder(FormType::class, null, ['csrf_protection' => false])
            ->add('password', NewPasswordType::class, $options)
            ->getForm();

        $form->submit(['password' => ['first' => $first, 'second' => $second]]);

        return $form;
    }

    /**
     * @return list<string>
     */
    private function getErrors(FormInterface $field): array
    {
        $errors = [];
        foreach ($field->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        return $errors;
    }
}
