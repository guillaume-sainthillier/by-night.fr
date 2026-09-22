<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Form;

use App\Form\Type\DateRangeType;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class DateRangeTypeTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideMalformedInputs(): iterable
    {
        yield 'array as start date' => [['from' => ['1'], 'to' => '2026-09-30']];
        yield 'array as end date' => [['from' => '2026-09-22', 'to' => ['1']]];
        yield 'string instead of the dates' => ['2026-09-22'];
    }

    #[DataProvider('provideMalformedInputs')]
    public function testMalformedInputMakesTheFormInvalid(mixed $submitted): void
    {
        $form = $this->createForm();

        $form->submit(['dateRange' => $submitted]);

        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->isValid());
    }

    public function testDatesAreStillRead(): void
    {
        $form = $this->createForm();

        $form->submit(['dateRange' => ['from' => '2026-09-22', 'to' => '2026-09-30']]);

        self::assertTrue($form->isValid());
    }

    /**
     * The type maps its fields on its parent's data, as in the city picker of the home page.
     */
    private function createForm(): FormInterface
    {
        return self::getContainer()->get(FormFactoryInterface::class)
            ->createBuilder(FormType::class, ['from' => null, 'to' => null], ['csrf_protection' => false])
            ->add('dateRange', DateRangeType::class, ['from_field' => '[from]', 'to_field' => '[to]'])
            ->getForm();
    }
}
