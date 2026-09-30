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
use App\Validator\Constraints\EventConstraint;
use App\Validator\Constraints\EventConstraintValidator;
use Override;
use Psr\Log\LogLevel;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Sentry receives every record of the "error" level with its context.
 *
 * @extends ConstraintValidatorTestCase<EventConstraintValidator>
 */
final class EventConstraintValidatorLoggingTest extends ConstraintValidatorTestCase
{
    private BufferingLogger $logger;

    #[Override]
    protected function createValidator(): EventConstraintValidator
    {
        $this->logger = new BufferingLogger();

        return new EventConstraintValidator($this->logger);
    }

    public function testARefusedSubmissionIsNeitherAnErrorNorLoggedWithItsContacts(): void
    {
        $dto = new EventDto();
        $dto->emailContacts = ['organiser@example.com'];
        $dto->reject = new Reject()->addReason(Reject::BAD_EVENT_DESCRIPTION);

        $this->validator->validate($dto, new EventConstraint());

        $this->buildViolation(new EventConstraint()->badEventDescrition)->atPath('property.path.description')->assertRaised();
        self::assertSame([[LogLevel::INFO, 'Event is rejected', ['reason' => Reject::VALID | Reject::BAD_EVENT_DESCRIPTION]]], $this->logger->cleanLogs());
    }
}
