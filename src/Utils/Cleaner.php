<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Dto\CityDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;

class Cleaner
{
    public function __construct(private readonly Util $util)
    {
    }

    public function cleanEvent(EventDto $dto): void
    {
        if (null === $dto->endDate) {
            $dto->endDate = $dto->startDate;
        }

        $dto->name = $this->clean($dto->name ?? '') ?: null;
        $dto->description = $this->clean($dto->description ?? '') ?: null;
        $dto->phoneContacts = $dto->phoneContacts ?: null;
        $dto->websiteContacts = $dto->websiteContacts ?: null;
        $dto->emailContacts = $dto->emailContacts ?: null;
        $dto->address = mb_substr($dto->address ?? '', 0, 255) ?: null;
        $dto->category = mb_substr($dto->category ?? '', 0, 128) ?: null;
        $dto->theme = mb_substr($dto->theme ?? '', 0, 128) ?: null;
        $dto->type = mb_substr($dto->type ?? '', 0, 128) ?: null;
        $dto->hours = mb_substr($dto->hours ?? '', 0, 255) ?: null;
        $dto->latitude = (float) $this->util->replaceNonNumericChars($dto->latitude) ?: null;
        $dto->longitude = (float) $this->util->replaceNonNumericChars($dto->longitude) ?: null;
    }

    private function clean(?string $string): string
    {
        return trim($string ?? '');
    }

    public function cleanPlace(PlaceDto $dto): void
    {
        $dto->name = $this->cleanNormalString($dto->name ?? '') ?: null;
        $dto->street = $this->cleanNormalString($dto->street ?? '') ?: null;
        $dto->latitude = (float) $this->util->replaceNonNumericChars($dto->latitude) ?: null;
        $dto->longitude = (float) $this->util->replaceNonNumericChars($dto->longitude) ?: null;
    }

    public function cleanCity(CityDto $dto): void
    {
        $dto->postalCode = $this->util->replaceNonNumericChars($dto->postalCode) ?: null;
        $dto->name = $this->cleanPostalString($dto->name ?? '') ?: null;
    }

    private function cleanNormalString(?string $string): string
    {
        return $this->cleanString($string, '');
    }

    /**
     * @param string|string[] $delimiters
     *
     * @psalm-param ''|array{0?: '-'} $delimiters
     */
    private function cleanString(string|null $string, array|string $delimiters = []): string
    {
        if (null === $string) {
            return '';
        }

        $step1 = $this->util->utf8TitleCase($string);
        $step2 = $this->util->deleteMultipleSpaces($step1);
        $step3 = $this->util->deleteSpaceBetween($step2, $delimiters);

        return trim($step3);
    }

    private function cleanPostalString(?string $string): string
    {
        return $this->cleanString($string, ['-']);
    }
}
