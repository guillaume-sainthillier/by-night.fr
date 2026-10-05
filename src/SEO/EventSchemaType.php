<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\Entity\Event;
use App\Enum\AgendaType;

/**
 * The schema.org subtype of an event. Search engines treat every subtype as an Event: a wrong one costs more than the
 * plain "Event", so only the signals the production events proved reliable count.
 */
final readonly class EventSchemaType
{
    /**
     * The kinds the sources give (Event::$type, comma-separated) that name one kind of outing: DATAtourisme's labels
     * of its own schema.org types, the SeeTickets genre, the SowProg event type and the OpenAgenda keywords.
     * Left out as noise: "Sport" (a mushroom walk, a play), "Commerce" (a winery's open day as often as a market),
     * "Spectacle" (stand-up, magic, musicals), and "Culture", "Communautaire", "Famille", "Business", "Art", which
     * the producers put on anything.
     */
    private const array KINDS = [
        'concert' => 'MusicEvent',
        'musique' => 'MusicEvent',
        'clubbing' => 'MusicEvent',
        'théâtre' => 'TheaterEvent',
        'danse' => 'DanceEvent',
        'exposition' => 'ExhibitionEvent',
        'festival' => 'Festival',
        'jeune public' => 'ChildrensEvent',
        // DATAtourisme's SportsCompetition: races, rallies, raids
        'compétition' => 'SportsEvent',
        'brocante' => 'SaleEvent',
        'vide grenier' => 'SaleEvent',
        'vide-grenier' => 'SaleEvent',
    ];

    /** The keywords of a workshop: "atelier, théâtre" is a class, not a play */
    private const array WORKSHOPS = ['atelier', 'ateliers', 'stage', 'cours', 'initiation'];

    public function resolve(Event $event): string
    {
        $labels = array_map(
            static fn (string $label): string => mb_strtolower(trim($label)),
            explode(',', (string) $event->getType()),
        );

        if ([] !== array_intersect($labels, self::WORKSHOPS)) {
            return 'Event';
        }

        $kinds = array_values(array_unique(array_filter(array_map(
            static fn (string $label): ?string => self::KINDS[$label] ?? null,
            $labels,
        ))));

        // Two kinds ("théâtre, concert") are a mixed bill or a misfiled event: the agenda types decide
        return 1 === \count($kinds) ? $kinds[0] : $this->fromAgendaTypes($event);
    }

    /**
     * From the agenda types the nightly classification found, which match words: only the combinations whose
     * production events are one kind of outing count.
     * - an exhibition is one, from the museum to the trade fair ("salon"), which ExhibitionEvent covers too, even when
     *   "spectacle" also made it a show; also a concert ("artistes"), it is as often a festival or a fundraiser;
     * - a concert is music, unless it is also a show (stand-up and plays among the musicals) or an exhibition;
     * - a show mixes theatre, stand-up, dance and circus, and the family and student types match words ("famille",
     *   "soirée") more than audiences: they say nothing of the kind of event.
     */
    private function fromAgendaTypes(Event $event): string
    {
        $types = array_filter(array_map(AgendaType::tryFrom(...), $event->getAgendaTypes()));

        $concert = \in_array(AgendaType::Concert, $types, true);
        $exhibition = \in_array(AgendaType::Exhibition, $types, true);

        return match (true) {
            $exhibition && !$concert => 'ExhibitionEvent',
            $concert && !$exhibition && !\in_array(AgendaType::Show, $types, true) => 'MusicEvent',
            default => 'Event',
        };
    }
}
