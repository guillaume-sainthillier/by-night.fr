<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Import;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\DtoFactory\EventDtoFactory;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Publisher;
use App\Entity\User;
use App\Handler\DoctrineEventHandler;
use App\Manager\MemberEventPublisher;
use App\Repository\CountryRepository;
use App\Repository\EventRepository;
use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\EventStatusLabels;
use App\Transfer\Sheet\SheetRow;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Reads an event of a member's spreadsheet (its rows) into what the event form would send: a new event of theirs, or
 * one of theirs (by ID, else by reference) with the file's columns written over it. It is then judged as the form's
 * is (the validation, the import pipeline's firewall), so a row passes the check exactly when the form would.
 *
 * A column the file lacks leaves the event's value alone; a column it has, left empty, clears the value (except the
 * picture, which an empty cell keeps, and the draft state, which a new event takes and an existing one keeps).
 */
final class EventRowMapper
{
    /** The violation paths of the event form, by the column that holds the value */
    private const array COLUMNS_BY_PATH = [
        'name' => EventColumn::Name,
        'description' => EventColumn::Description,
        'startDate' => EventColumn::StartDate,
        'shortcut' => EventColumn::StartDate,
        'place' => EventColumn::Place,
        'place.name' => EventColumn::Place,
        'place.city.name' => EventColumn::City,
        'place.city.postalCode' => EventColumn::PostalCode,
        'place.country' => EventColumn::Country,
        'category.name' => EventColumn::Category,
    ];

    /** @var array<string, Country>|null by folded code and names */
    private ?array $countries = null;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CountryRepository $countryRepository,
        private readonly EventDtoFactory $eventDtoFactory,
        private readonly MemberEventPublisher $memberEventPublisher,
        private readonly DoctrineEventHandler $doctrineEventHandler,
        private readonly ValidatorInterface $validator,
        #[Autowire(env: 'S3_PUBLIC_URL')]
        private readonly string $imagesUrl,
    ) {
    }

    public function map(EventRowGroup $group, User $user, Publisher $publisher): MappedEvent
    {
        $first = $group->first();
        $reference = $group->reference();
        $errors = $group->errors;

        $event = [] === $errors ? $this->findEvent($group, $publisher, $errors) : null;
        $dto = null === $event ? $this->memberEventPublisher->createDto($user) : $this->eventDtoFactory->create($event);
        $before = null === $event ? null : self::signature($dto);
        $draft = null;

        if ([] === $errors) {
            try {
                $values = $this->eventValues($group);
                $this->applyEventValues($dto, $values, null === $event);
                $draft = isset($values[EventColumn::Draft->value]) ? CellParser::bool($values[EventColumn::Draft->value], EventColumn::Draft) : null;
            } catch (InvalidCellException $e) {
                $errors[] = RowProblem::at($e->row ?? $first, $e->column, $e->getMessage());
            }

            $errors = [...$errors, ...$this->applySessions($dto, $group)];
        }

        if ([] === $errors) {
            $errors = $this->judge($dto, $first);
        }

        $changed = null === $before || self::signature($dto) !== $before || (null !== $draft && $draft !== $event?->isDraft())
            || (null !== $reference && $reference !== $event?->getExternalReference());

        return new MappedEvent($group, $dto, $event?->getId(), $reference, $draft, $changed, $errors);
    }

    /**
     * The publisher's event the rows update: by its ID, else by their reference. Null for a new event.
     *
     * @param list<RowProblem> $errors
     */
    private function findEvent(EventRowGroup $group, Publisher $publisher, array &$errors): ?Event
    {
        $reference = $group->reference();
        $byReference = null === $reference ? null : $this->eventRepository->findOneByPublisherAndReference($publisher, $reference);
        $id = $group->id();
        if (null === $id) {
            return $byReference;
        }

        $event = $this->eventRepository->find($id);
        if (null === $event || $event->getPublisher()?->getId() !== $publisher->getId()) {
            $errors[] = RowProblem::at($group->first(), EventColumn::Id, \sprintf('L’événement %d n’est pas l’un des vôtres : laissez la colonne vide pour en créer un.', $id));

            return null;
        }

        if (null !== $byReference && $byReference->getId() !== $id) {
            $errors[] = RowProblem::at($group->first(), EventColumn::Reference, \sprintf('La référence « %s » est déjà celle d’un autre de vos événements (ID %d).', $reference, $byReference->getId()));

            return null;
        }

        return $event;
    }

    /**
     * The values of the event's own columns, from its rows: the first row that fills a column gives its value, a
     * later row may repeat it or leave it empty, but not say something else.
     *
     * @return array<string, string> by EventColumn value, for the columns the file has
     */
    private function eventValues(EventRowGroup $group): array
    {
        $values = [];
        $rows = [];
        foreach (EventColumn::cases() as $column) {
            if ($column->isSession() || !$group->first()->has($column)) {
                continue;
            }

            $values[$column->value] = '';
            foreach ($group->rows as $row) {
                $value = $row->get($column);
                if ('' === $value) {
                    continue;
                }

                if ('' === $values[$column->value]) {
                    $values[$column->value] = $value;
                    $rows[$column->value] = $row->number;
                } elseif ($value !== $values[$column->value]) {
                    throw new InvalidCellException($column, \sprintf('« %s » contredit la ligne %d (« %s ») : les lignes d’un même événement doivent dire la même chose, ou laisser la colonne vide.', $value, $rows[$column->value], $values[$column->value]), $row);
                }
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $values the columns the file has
     */
    private function applyEventValues(EventDto $dto, array $values, bool $new): void
    {
        $value = static fn (EventColumn $column): ?string => \array_key_exists($column->value, $values) ? $values[$column->value] : null;
        $text = static fn (EventColumn $column, ?string $current): ?string => match ($value($column)) {
            null => $current,
            '' => null,
            default => $value($column),
        };

        $dto->name = $text(EventColumn::Name, $dto->name);
        $dto->description = $text(EventColumn::Description, $dto->description);
        $dto->prices = $text(EventColumn::Prices, $dto->prices);
        $dto->statusMessage = $text(EventColumn::StatusMessage, $dto->statusMessage);

        if (null !== $value(EventColumn::TicketUrl)) {
            $dto->ticketUrl = CellParser::url($value(EventColumn::TicketUrl), EventColumn::TicketUrl);
        }

        // An empty cell keeps the picture; one of ours (an export's) too, or each round trip would download it again
        $imageUrl = CellParser::url($value(EventColumn::ImageUrl) ?? '', EventColumn::ImageUrl);
        if (null !== $imageUrl && !$this->isOwnImage($imageUrl)) {
            $dto->imageUrl = $imageUrl;
        }

        if (null !== $value(EventColumn::Websites)) {
            $dto->websiteContacts = CellParser::urls($value(EventColumn::Websites), EventColumn::Websites);
        }

        if (null !== $value(EventColumn::Phones)) {
            $dto->phoneContacts = CellParser::list($value(EventColumn::Phones));
        }

        if (null !== $value(EventColumn::Emails)) {
            $dto->emailContacts = CellParser::emails($value(EventColumn::Emails), EventColumn::Emails);
        }

        if (null !== $value(EventColumn::Category)) {
            $dto->category = '' === $value(EventColumn::Category) ? null : TagDto::fromString($value(EventColumn::Category));
        }

        if (null !== $value(EventColumn::Themes)) {
            $dto->themes = array_map(TagDto::fromString(...), CellParser::list($value(EventColumn::Themes)));
        }

        if (null !== $value(EventColumn::Status)) {
            $status = EventStatusLabels::fromCell($value(EventColumn::Status));
            if (false === $status) {
                throw new InvalidCellException(EventColumn::Status, \sprintf('« %s » n’est pas un statut : %s, ou rien si l’événement a lieu comme prévu.', $value(EventColumn::Status), implode(', ', array_keys(EventStatusLabels::IMPORTABLE))));
            }

            $dto->status = $status;
        }

        // The message details a status: as the form, none without one
        if (null === $dto->status) {
            $dto->statusMessage = null;
        }

        $this->applyPlace($dto, $value, $new);
    }

    /**
     * @param callable(EventColumn): ?string $value
     */
    private function applyPlace(EventDto $dto, callable $value, bool $new): void
    {
        $columns = [EventColumn::Place, EventColumn::Street, EventColumn::PostalCode, EventColumn::City, EventColumn::Country];
        if (!$new && [] === array_filter($columns, static fn (EventColumn $column): bool => null !== $value($column))) {
            return;
        }

        $place = $dto->place ?? new PlaceDto();
        $city = $place->city ?? new CityDto();
        $place->name = $value(EventColumn::Place) ?? $place->name;
        $place->street = $value(EventColumn::Street) ?? $place->street;
        $city->name = $value(EventColumn::City) ?? $city->name;
        $city->postalCode = $value(EventColumn::PostalCode) ?? $city->postalCode;
        foreach ([&$place->name, &$place->street, &$city->name, &$city->postalCode] as &$field) {
            $field = '' === $field ? null : $field;
        }
        unset($field);

        $code = $value(EventColumn::Country);
        if (null !== $code || null === $place->country) {
            $country = $this->findCountry($code ?: 'FR');
            if (null === $country) {
                throw new InvalidCellException(EventColumn::Country, \sprintf('« %s » n’est pas un pays de By Night : écrivez son code (FR, BE, CH…).', $code));
            }

            $countryDto = new CountryDto();
            $countryDto->entityId = $country->getId();
            $countryDto->code = $country->getId();
            $place->country = $countryDto;
        }

        $city->country = $place->country;
        $place->city = $city;
        $dto->place = $place;
    }

    /**
     * The event's dates, one session per row: a single row is the event's own dates and times, several rows are
     * its sessions, the event spanning from the first to the last.
     *
     * @return list<RowProblem>
     */
    private function applySessions(EventDto $dto, EventRowGroup $group): array
    {
        $errors = [];
        $sessions = [];
        foreach ($group->rows as $row) {
            try {
                $sessions[] = $this->session($row);
            } catch (InvalidCellException $e) {
                $errors[] = RowProblem::at($row, $e->column, $e->getMessage());
            }
        }

        if ([] !== $errors) {
            return $errors;
        }

        usort($sessions, static fn (EventTimesheetDto $a, EventTimesheetDto $b): int => [$a->startAt, $a->startTime] <=> [$b->startAt, $b->startTime]);
        $first = $sessions[0];
        $last = array_reduce($sessions, static fn (EventTimesheetDto $carry, EventTimesheetDto $session): EventTimesheetDto => $session->endAt >= $carry->endAt ? $session : $carry, $first);

        $dto->startDate = $first->startAt;
        $dto->endDate = $last->endAt;
        $dto->startTime = $first->startTime;
        $dto->endTime = $last->endTime;
        if (1 === \count($sessions)) {
            $dto->hours = $first->hours;
            $dto->timesheets = [];
        } else {
            $dto->hours = null;
            $dto->timesheets = $sessions;
        }

        return [];
    }

    private function session(SheetRow $row): EventTimesheetDto
    {
        $start = CellParser::date($row->get(EventColumn::StartDate), EventColumn::StartDate)
            ?? throw new InvalidCellException(EventColumn::StartDate, 'La date de début manque.');
        $end = CellParser::date($row->get(EventColumn::EndDate), EventColumn::EndDate) ?? $start;
        if ($end < $start) {
            throw new InvalidCellException(EventColumn::EndDate, 'La date de fin précède la date de début.');
        }

        $session = new EventTimesheetDto();
        $session->startAt = $start;
        $session->endAt = $end;
        $session->startTime = CellParser::time($row->get(EventColumn::StartTime), EventColumn::StartTime);
        $session->endTime = CellParser::time($row->get(EventColumn::EndTime), EventColumn::EndTime);
        $session->hours = '' === $row->get(EventColumn::Hours) ? null : $row->get(EventColumn::Hours);

        return $session;
    }

    /**
     * The form's verdict: its constraints, and the firewall's (EventConstraintValidator).
     *
     * @return list<RowProblem>
     */
    private function judge(EventDto $dto, SheetRow $first): array
    {
        $this->doctrineEventHandler->judge($dto);
        $errors = [];
        foreach ($this->validator->validate($dto) as $violation) {
            $path = $violation->getPropertyPath();
            $column = self::COLUMNS_BY_PATH[$path] ?? (str_starts_with($path, 'themes') ? EventColumn::Themes : null);
            $errors[] = RowProblem::at($first, $column, (string) $violation->getMessage());
        }

        return $errors;
    }

    private function findCountry(string $value): ?Country
    {
        if (null === $this->countries) {
            $this->countries = [];
            foreach ($this->countryRepository->findAll() as $country) {
                foreach ([$country->getId(), $country->getName(), $country->getDisplayName()] as $name) {
                    $this->countries[self::fold((string) $name)] = $country;
                }
            }
        }

        return $this->countries[self::fold($value)] ?? null;
    }

    private function isOwnImage(string $url): bool
    {
        return parse_url($url, \PHP_URL_HOST) === parse_url($this->imagesUrl, \PHP_URL_HOST);
    }

    private static function fold(string $value): string
    {
        return mb_strtolower((string) transliterator_transliterate('Any-Latin; Latin-ASCII', trim($value)));
    }

    /**
     * What the spreadsheet can change on an event, to tell whether the rows change it.
     */
    private static function signature(EventDto $dto): string
    {
        $date = static fn (?DateTimeImmutable $date, string $format = 'Y-m-d'): ?string => $date?->format($format);

        return serialize([
            $dto->name, $dto->description, $dto->prices, $dto->ticketUrl, $dto->imageUrl, $dto->status, $dto->statusMessage,
            $dto->websiteContacts, $dto->phoneContacts, $dto->emailContacts,
            $dto->category?->name, array_map(static fn (TagDto $theme): ?string => $theme->name, $dto->themes),
            $date($dto->startDate), $date($dto->endDate), $date($dto->startTime, 'H:i'), $date($dto->endTime, 'H:i'), $dto->hours,
            array_map(static fn (EventTimesheetDto $session): array => [$date($session->startAt), $date($session->endAt), $date($session->startTime, 'H:i'), $date($session->endTime, 'H:i'), $session->hours], $dto->timesheets),
            $dto->place?->name, $dto->place?->street, $dto->place?->city?->name, $dto->place?->city?->postalCode, $dto->place?->country?->code,
        ]);
    }
}
