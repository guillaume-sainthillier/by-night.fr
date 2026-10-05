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
use App\Utils\HoursLabel;
use App\Utils\HtmlFormatter;
use App\Utils\Util;
use DateTimeImmutable;
use DateTimeInterface;

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
        $dto->ticketUrl = $this->fitUrl($this->htmlFormatter->ensureProtocol($dto->ticketUrl), 1024);
        $dto->performers = $this->cleanPerformers($dto->performers);
        $dto->phoneContacts = $dto->phoneContacts ?: null;
        $dto->websiteContacts = $this->cleanWebsites($dto->websiteContacts) ?: null;
        $dto->emailContacts = $dto->emailContacts ?: null;
        $dto->address = mb_substr($dto->address ?? '', 0, 255) ?: null;
        $dto->type = mb_substr($dto->type ?? '', 0, 128) ?: null;
        $dto->statusMessage = $this->fit($this->clean($dto->statusMessage), 2000);

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

        $this->cleanEventTimes($dto);
    }

    /**
     * The times of the event, its timesheets once cleaned: its own slot when it is one session (no timesheets), else
     * the span of its sessions.
     */
    public function cleanEventTimes(EventDto $dto): void
    {
        if ([] === $dto->timesheets) {
            [$dto->startTime, $dto->endTime, $dto->hours] = $this->cleanSlot($dto->startTime, $dto->endTime, $dto->hours);
        } else {
            [$dto->startTime, $dto->endTime] = $this->span($dto->timesheets);
        }
    }

    /**
     * The feeds repeat an artist in another case ("Seth|SETH") and encode their names ("PETER HOOK &amp; THE
     * LIGHT"): each one once, as first spelled.
     *
     * @param list<string> $performers
     *
     * @return list<string>
     */
    private function cleanPerformers(array $performers): array
    {
        $cleaned = [];
        foreach ($performers as $performer) {
            // Spaces only: the names keep their case ("PLK", "47TER")
            $performer = $this->fit(mb_trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($performer, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'))), 255);
            if (null !== $performer) {
                $cleaned[mb_strtolower($performer)] ??= $performer;
            }
        }

        return array_values($cleaned);
    }

    public function cleanEventTimesheet(EventTimesheetDto $dto): void
    {
        $dto->hours = mb_substr($dto->hours ?? '', 0, 255) ?: null;
        [$dto->startTime, $dto->endTime, $dto->hours] = $this->cleanSlot($dto->startTime, $dto->endTime, $dto->hours);
    }

    /**
     * The times of a session, and its label once they are out of it:
     * - a label that only states a slot ("À 20h30", "De 10h00 à 18h00") gives the times when there are none, and
     *   goes: the label shown is built from the times;
     * - the whole day is no time: midnight to 23:59, or 02:00 to 01:59 as OpenAgenda shifts it, as well as a
     *   midnight start alone, which is how the feeds write a day without a time;
     * - an end equal to the start is none, a placeholder many producers send.
     *
     * @return array{0: DateTimeInterface|null, 1: DateTimeInterface|null, 2: string|null}
     */
    private function cleanSlot(?DateTimeInterface $startTime, ?DateTimeInterface $endTime, ?string $hours): array
    {
        $slot = HoursLabel::parse($hours);
        if (null !== $slot) {
            if (null === $startTime && null === $endTime) {
                [$startTime, $endTime] = $slot;
            }

            $hours = null;
        }

        $start = $startTime?->format('H:i');
        $end = $endTime?->format('H:i');
        if (null !== $startTime && null !== $endTime && DateTimeImmutable::createFromInterface($startTime)->modify('-1 minute')->format('H:i') === $end) {
            return [null, null, $hours];
        }

        if ($start === $end) {
            $endTime = null;
        }

        if ('00:00' === $start && null === $endTime) {
            $startTime = null;
        }

        return [$startTime, $endTime, $hours];
    }

    /**
     * The span of the sessions: the time the first one starts (the earliest day, then the earliest time given that
     * day) and the time the last one ends (the latest day, then the latest time given that day). Null when that day
     * gives none.
     *
     * @param list<EventTimesheetDto> $timesheets
     *
     * @return array{0: DateTimeInterface|null, 1: DateTimeInterface|null}
     */
    private function span(array $timesheets): array
    {
        $first = null;
        $last = null;
        foreach ($timesheets as $timesheet) {
            $startDay = $timesheet->startAt?->format('Y-m-d');
            if (null === $startDay) {
                continue;
            }

            $endDay = $timesheet->endAt?->format('Y-m-d') ?? $startDay;
            $start = $timesheet->startTime?->format('H:i');
            $end = $timesheet->endTime?->format('H:i');
            if (null === $first || $startDay < $first[0] || ($startDay === $first[0] && null !== $start && (null === $first[1] || $start < $first[1]))) {
                $first = [$startDay, $start, $timesheet->startTime];
            }

            if (null === $last || $endDay > $last[0] || ($endDay === $last[0] && null !== $end && (null === $last[1] || $end > $last[1]))) {
                $last = [$endDay, $end, $timesheet->endTime];
            }
        }

        return [$first[2] ?? null, $last[2] ?? null];
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
    private function cleanWebsites(?array $websites): array
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
