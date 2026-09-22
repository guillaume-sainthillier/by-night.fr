<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Dto\CityDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Utils\HtmlFormatter;
use App\Utils\Util;

final readonly class Cleaner
{
    public function __construct(
        private Util $util,
        private HtmlFormatter $htmlFormatter,
    ) {
    }

    public function cleanEvent(EventDto $dto): void
    {
        $dto->endDate ??= $dto->startDate;

        // Every value must fit its column: MySQL refuses a longer one and the whole batch fails with it
        $dto->name = $this->fit($this->clean($dto->name ?? ''), 255);
        $dto->description = $this->clean($dto->description ?? '') ?: null;
        $dto->source = $this->fitUrl($dto->source, 256);
        $dto->imageUrl = $this->fitUrl($dto->imageUrl, 255);
        $dto->phoneContacts = $dto->phoneContacts ?: null;
        $dto->websiteContacts = $this->cleanWebsites($dto->websiteContacts) ?: null;
        $dto->emailContacts = $dto->emailContacts ?: null;
        $dto->address = mb_substr($dto->address ?? '', 0, 255) ?: null;
        $dto->type = mb_substr($dto->type ?? '', 0, 128) ?: null;

        // Clean category TagDto
        if (null !== $dto->category) {
            $dto->category->name = mb_substr(trim($dto->category->name ?? ''), 0, 128) ?: null;
            if (null === $dto->category->name) {
                $dto->category = null;
            }
        }

        // Clean themes TagDto array
        $dto->themes = array_values(array_filter($dto->themes, static function (TagDto $tagDto): bool {
            if (null === $tagDto->name) {
                return false;
            }

            $tagDto->name = mb_substr(trim($tagDto->name), 0, 128) ?: null;

            return null !== $tagDto->name;
        }));
        $dto->hours = mb_substr($dto->hours ?? '', 0, 255) ?: null;
        $dto->prices = mb_substr($dto->prices ?? '', 0, 255) ?: null;
        $dto->latitude = (float) $this->util->replaceNonNumericChars($dto->latitude) ?: null;
        $dto->longitude = (float) $this->util->replaceNonNumericChars($dto->longitude) ?: null;

        // Clean timesheets
        foreach ($dto->timesheets as $timesheet) {
            $this->cleanEventTimesheet($timesheet);
        }
    }

    public function cleanEventTimesheet(EventTimesheetDto $dto): void
    {
        $dto->hours = mb_substr($dto->hours ?? '', 0, 255) ?: null;
    }

    /**
     * Feeds pack several websites into one value ("www.a.fr www.b.fr", "http://a.fr;http://b.fr",
     * "https://a.fr/billets Etienne ANDRE") or ship text and phone numbers as websites. A value that can
     * be linked is kept whole, as typed (spaces in its query string belong to the URL); any other is
     * split, and only the parts that can be linked are kept.
     *
     * @param string[]|null $websites
     *
     * @return string[]
     */
    public function cleanWebsites(?array $websites): array
    {
        $cleaned = [];
        foreach ($websites ?? [] as $website) {
            $website = mb_trim((string) $website);
            if (null !== $this->htmlFormatter->ensureProtocol($website)) {
                $cleaned[] = $website;

                continue;
            }

            foreach (preg_split('~[\s;,]+~u', $website, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if (null !== $this->htmlFormatter->ensureProtocol($part)) {
                    $cleaned[] = $part;
                }
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function clean(?string $string): string
    {
        return trim($string ?? '');
    }

    private function fit(string $string, int $length): ?string
    {
        return rtrim(mb_substr($string, 0, $length)) ?: null;
    }

    /**
     * A link cut to its column would lead nowhere: one too long for it is dropped.
     */
    private function fitUrl(?string $url, int $length): ?string
    {
        $url = trim($url ?? '');

        return '' === $url || mb_strlen($url) > $length ? null : $url;
    }

    public function cleanPlace(PlaceDto $dto): void
    {
        $dto->name = $this->fit($this->cleanNormalString($dto->name ?? ''), 255);
        $dto->street = $this->fit($this->cleanNormalString($dto->street ?? ''), 127);
        $dto->latitude = (float) $this->util->replaceNonNumericChars($dto->latitude) ?: null;
        $dto->longitude = (float) $this->util->replaceNonNumericChars($dto->longitude) ?: null;
    }

    public function cleanCity(CityDto $dto): void
    {
        // Digits only, as PostalCodeChecker reads them: "F-31000" kept its dash and missed the zip lookup
        $dto->postalCode = preg_replace('#\D#', '', (string) $dto->postalCode) ?: null;
        $dto->name = $this->fit($this->cleanPostalString($dto->name ?? ''), 127);
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
    private function cleanString(?string $string, array|string $delimiters = []): string
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
