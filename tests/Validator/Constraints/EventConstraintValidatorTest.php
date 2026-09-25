<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Validator\Constraints;

use App\Dto\EventDto;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;
use App\Validator\Constraints\EventConstraint;
use App\Validator\Constraints\EventConstraintValidator;
use Override;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The event form shows the verdict of the Firewall (DoctrineEventHandler::judge()) as
 * violations on the fields it is about.
 */
final class EventConstraintValidatorTest extends AppKernelTestCase
{
    #[Override]
    protected function tearDown(): void
    {
        self::getContainer()->get(EventConstraintValidator::class)->batchReset();
        parent::tearDown();
    }

    public function testAnEventTheFirewallAcceptsIsValid(): void
    {
        self::assertCount(0, $this->validate(new Reject()));
        self::assertCount(0, $this->validate(null));
    }

    public function testEachReasonIsShownOnItsField(): void
    {
        $violations = $this->validate(new Reject()
            ->addReason(Reject::BAD_EVENT_NAME)
            ->addReason(Reject::BAD_EVENT_DATE_INTERVAL)
            ->addReason(Reject::SPAM_EVENT_DESCRIPTION)
            ->addReason(Reject::NO_PLACE_PROVIDED)
            ->addReason(Reject::BAD_PLACE_NAME)
            ->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE));

        $constraint = new EventConstraint();
        self::assertSame([
            'name' => $constraint->badEventName,
            'shortcut' => $constraint->badEventDateInterval,
            'description' => $constraint->spamEventDescription,
            'place' => $constraint->noPlaceProvided,
            'place.name' => $constraint->badPlaceName,
            'place.city.postalCode' => $constraint->badPlacePostalCode,
        ], self::byPath($violations));
    }

    /**
     * Someone editing their event wants it saved, whether or not the source changed since.
     */
    public function testAnUnchangedEventIsValidInTheForm(): void
    {
        self::assertCount(0, $this->validate(new Reject()->addReason(Reject::NO_NEED_TO_UPDATE)));
    }

    public function testAnUnchangedEventIsRefusedWhenTheUpdateIsChecked(): void
    {
        self::getContainer()->get(EventConstraintValidator::class)->setUpdatabilityCkeck(true);

        $violations = $this->validate(new Reject()->addReason(Reject::NO_NEED_TO_UPDATE));

        self::assertSame(['' => new EventConstraint()->noNeedToUpdate], self::byPath($violations));
    }

    public function testTheUpdateCheckEndsWithTheBatch(): void
    {
        $validator = self::getContainer()->get(EventConstraintValidator::class);
        $validator->setUpdatabilityCkeck(true);
        $validator->batchReset();

        self::assertCount(0, $this->validate(new Reject()->addReason(Reject::NO_NEED_TO_UPDATE)));
    }

    public function testADeletedEventOnlySaysSo(): void
    {
        $violations = $this->validate(new Reject()
            ->addReason(Reject::EVENT_DELETED)
            ->addReason(Reject::BAD_EVENT_NAME));

        self::assertSame(['' => new EventConstraint()->eventDeleted], self::byPath($violations));
    }

    public function testAReasonWithoutAMessageStillRefusesTheEvent(): void
    {
        $violations = $this->validate(new Reject()->addReason(Reject::BAD_USER));

        self::assertCount(1, $violations);
        self::assertStringContainsString((string) (Reject::VALID | Reject::BAD_USER), (string) $violations->get(0)->getMessage());
    }

    private function validate(?Reject $reject): ConstraintViolationListInterface
    {
        $dto = new EventDto();
        $dto->reject = $reject;

        return self::getContainer()->get(ValidatorInterface::class)->validate($dto, new EventConstraint());
    }

    /**
     * @return array<string, string>
     */
    private static function byPath(ConstraintViolationListInterface $violations): array
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
