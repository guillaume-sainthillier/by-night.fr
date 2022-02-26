<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Dto\EventDto;
use App\Entity\Place;

class Cleaner
{
    public function __construct(private Util $util)
    {
    }

    public function cleanEvent(EventDto $dto): void
    {
        if (null === $dto->endDate) {
            $dto->endDate = $dto->startDate;
        }

        $dto->name = ($this->clean($dto->name) ?: null);
        $dto->description = ($this->clean($dto->description) ?: null);
        $dto->phoneContacts = ($dto->phoneContacts ?: null);
        $dto->websiteContacts = ($dto->websiteContacts ?: null);
        $dto->emailContacts = ($dto->emailContacts ?: null);
        $dto->address = (mb_substr($dto->address, 0, 255) ?: null);
        $dto->category = (mb_substr($dto->category, 0, 128) ?: null);
        $dto->theme = (mb_substr($dto->theme, 0, 128) ?: null);
        $dto->type = (mb_substr($dto->type, 0, 128) ?: null);
        $dto->hours = (mb_substr($dto->hours, 0, 255) ?: null);
    }

    private function clean(?string $string): string
    {
        return trim($string);
    }

    public function cleanPlace(Place $place): void
    {
        $place
            ->setNom($this->cleanNormalString($place->getNom()) ?: null)
            ->setRue($this->cleanNormalString($place->getRue()) ?: null)
            ->setLatitude((float) ($this->util->replaceNonNumericChars($place->getLatitude())) ?: null)
            ->setLongitude((float) ($this->util->replaceNonNumericChars($place->getLongitude())) ?: null)
            ->setVille($this->cleanPostalString($place->getVille()) ?: null)
            ->setCodePostal($this->util->replaceNonNumericChars($place->getCodePostal()) ?: null);
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
