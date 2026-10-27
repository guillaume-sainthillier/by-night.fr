<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Entity\Event;
use App\SEO\EventSEO;
use App\Utils\SessionHours;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * The social image of an event, 1200×630: its city and category, name, date, hours and venue, with its poster and
 * the figures of the day in the card, on the fixed layer assets/og/event.png (design/og/event.html, `pnpm og-image`,
 * whose comments give the boxes this class draws in). The poster alone, as og:image, was cropped to the 1.91:1 of the
 * networks, often through its title.
 *
 * A card is drawn on the first request of its URL (EventCardController) into the share dir, kept across deploys. Its
 * file name carries a hash of everything drawn on it: a new date, title or poster makes a new URL, which the networks
 * fetch again, and the old file is deleted.
 */
final readonly class EventCard
{
    // Bump to redraw every card after a change of the drawing or of assets/og/event.png
    private const string REVISION = '1';

    private const string INK = '#191c22';
    private const string MUTED = '#5a413b';
    private const string PRIMARY = '#c43d1d';
    private const string PRIMARY_SOFT = '#ffdad2';
    private const string SURFACE_LOW = '#f2f3fd';

    /** The box of the name, the date and the venue: (72, 140), 668 × 330 */
    private const int TEXT_X = 72;
    private const int TEXT_TOP = 140;
    private const int TEXT_WIDTH = 668;
    private const int TEXT_HEIGHT = 330;

    /** The card: the poster in (804, 156) 308 × 150, the venue and its city centred on x 958 */
    private const int CARD_CENTER = 958;
    private const array FIGURE_CENTERS = [845, 958, 1071];

    public function __construct(
        private EventSEO $eventSEO,
        private EventProfilePicture $eventProfilePicture,
        private StorageInterface $storage,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%kernel.share_dir%/og')]
        private string $directory,
    ) {
    }

    /**
     * The hash of what the card of the event shows: part of its URL, so a change makes a new one.
     */
    public function hash(Event $event): string
    {
        return substr(md5(serialize([self::REVISION, $this->texts($event), $this->posterKey($event)])), 0, 10);
    }

    /**
     * The path of the card of the event, drawn when it is not yet.
     */
    public function path(Event $event): string
    {
        $path = \sprintf('%s/%d-%s.jpg', $this->directory, $event->getId(), $this->hash($event));
        if (!is_file($path)) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($this->directory);
            // The card of a former date or poster, which no page links to anymore
            $filesystem->remove(glob(\sprintf('%s/%d-*.jpg', $this->directory, $event->getId())) ?: []);
            $this->draw($event, $path);
        }

        return $path;
    }

    /**
     * @return array{badges: list<string>, title: string, date: string, hours: ?string, venue: ?string, place: ?string, city: ?string, figures: list<string>}
     */
    private function texts(Event $event): array
    {
        $sessions = $event->getSessions();
        $first = $sessions[0] ?? null;
        $city = $event->getPlaceCity();

        return [
            'badges' => array_values(array_filter([$city, $event->getCategory()?->getName()])),
            // French typography: a no-break space binds « : ; ? ! » to the word before, so the line never starts with one
            'title' => (string) preg_replace('/\s+([:;?!»])/u', "\u{00A0}$1", trim((string) $this->eventSEO->getEventShortTitle($event))),
            'date' => ucfirst($this->eventSEO->getEventDate($event)),
            'hours' => SessionHours::ofEvent($event),
            'venue' => implode(', ', array_filter([$event->getPlaceName(), $city])) ?: null,
            'place' => $event->getPlaceName(),
            'city' => $city,
            'figures' => [
                $event->getStartDate()?->format('d/m') ?? '–',
                (null === $first ? null : SessionHours::start($first)) ?? '–',
                $city ?? '–',
            ],
        ];
    }

    /**
     * What identifies the poster the page shows: the file of an upload, the placeholder of its source otherwise.
     */
    private function posterKey(Event $event): string
    {
        return $this->eventProfilePicture->getPicturePathAndSource($event)['path'];
    }

    /**
     * The poster as an image: read from the bucket for an upload, from the build for a source's placeholder.
     */
    private function poster(Event $event): ?Imagick
    {
        ['source' => $source, 'path' => $path, 'entity' => $entity, 'field' => $field] = $this->eventProfilePicture->getPicturePathAndSource($event);
        $image = new Imagick();
        if ('upload' === $source) {
            $stream = $this->storage->resolveStream($entity, $field);
            if (!\is_resource($stream)) {
                return null;
            }
            $image->readImageBlob((string) stream_get_contents($stream));
            fclose($stream);
        } else {
            $file = $this->projectDir . '/public' . parse_url($path, \PHP_URL_PATH);
            if (!is_file($file)) {
                return null;
            }
            $image->readImage($file);
        }
        // The first frame only: a GIF poster may be animated
        $image->setIteratorIndex(0);

        return $image;
    }

    private function draw(Event $event, string $path): void
    {
        $texts = $this->texts($event);
        $card = new Imagick($this->projectDir . '/assets/og/event.png');
        $card->transformImageColorspace(Imagick::COLORSPACE_SRGB);

        $this->drawBadges($card, $texts['badges']);
        $this->drawIntro($card, $texts);
        $this->drawPoster($card, $this->poster($event));
        if (null !== $texts['place']) {
            $this->centered($card, $texts['place'], 'ExtraBold', 22, self::INK, self::CARD_CENTER, 340, 300);
        }
        if (null !== $texts['city']) {
            $this->centered($card, $texts['city'], 'SemiBold', 17, self::MUTED, self::CARD_CENTER, 366, 300);
        }
        foreach ($texts['figures'] as $i => $value) {
            $this->centered($card, $value, 'ExtraBold', 22, 1 === $i ? self::INK : self::PRIMARY, self::FIGURE_CENTERS[$i], 440, 100);
        }

        $card->setImageFormat('jpeg');
        $card->setImageCompressionQuality(88);
        $card->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        $card->stripImage();
        // Written aside then moved: a request reading it meanwhile never gets half a file
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $card->writeImage($tmp);
        rename($tmp, $path);
        $card->clear();
    }

    /**
     * The city (in capitals, after a dot) and the category, as pills from (72, 64).
     *
     * @param list<string> $badges
     */
    private function drawBadges(Imagick $card, array $badges): void
    {
        $x = self::TEXT_X;
        foreach ($badges as $i => $label) {
            $label = 0 === $i ? mb_strtoupper($label) : $label;
            $font = $this->font('ExtraBold', 16, 0 === $i ? self::INK : self::PRIMARY, 0.04);
            $width = (int) ceil($this->width($card, $font, $label)) + 32 + (0 === $i ? 18 : 0);
            $pill = new ImagickDraw();
            $pill->setFillColor(new ImagickPixel(0 === $i ? self::PRIMARY_SOFT : self::SURFACE_LOW));
            $pill->roundRectangle($x, 64, $x + $width, 100, 18, 18);
            if (0 === $i) {
                $pill->setFillColor(new ImagickPixel(self::PRIMARY));
                $pill->circle($x + 21, 82, $x + 26, 82);
            }
            $card->drawImage($pill);
            $card->annotateImage($font, $x + 16 + (0 === $i ? 18 : 0), 88, 0, $label);
            $x += $width + 10;
        }
    }

    /**
     * The name (up to three lines, smaller when long), the date in the red of the site, the hours, and the venue,
     * centred in the text box.
     *
     * @param array{title: string, date: string, hours: ?string, venue: ?string} $texts
     */
    private function drawIntro(Imagick $card, array $texts): void
    {
        $size = mb_strlen($texts['title']) > 40 ? 50 : 60;
        do {
            $title = $this->font('ExtraBold', $size, self::INK);
            $lines = $this->wrap($card, $title, $texts['title'], self::TEXT_WIDTH);
        } while (\count($lines) > 3 && ($size -= 4) >= 34);
        $titleHeight = \count($lines) * $size * 1.06;

        $dateSize = 32;
        while ($dateSize > 22 && $this->width($card, $this->font('ExtraBold', $dateSize, self::PRIMARY), $texts['date']) > self::TEXT_WIDTH) {
            $dateSize -= 2;
        }
        $details = array_values(array_filter([$texts['hours'], $texts['venue']]));
        $height = $titleHeight + 22 + $dateSize * 1.2 + \count($details) * (8 + 24 * 1.35);

        $top = self::TEXT_TOP + max(0, (self::TEXT_HEIGHT - $height) / 2);
        foreach ($lines as $i => $line) {
            $card->annotateImage($title, self::TEXT_X, $top + ($i + 0.82) * $size * 1.06, 0, $line);
        }
        $top += $titleHeight + 22;

        $card->annotateImage($this->font('ExtraBold', $dateSize, self::PRIMARY), self::TEXT_X, $top + $dateSize * 0.95, 0, $texts['date']);
        $top += $dateSize * 1.2;

        foreach ($details as $detail) {
            $font = $this->font('SemiBold', 24, self::MUTED);
            $card->annotateImage($font, self::TEXT_X, $top + 8 + 24 * 1.05, 0, $this->fit($card, $font, $detail, self::TEXT_WIDTH));
            $top += 8 + 24 * 1.35;
        }
    }

    /**
     * The poster cropped to its box, (804, 156) 308 × 150, with rounded corners; the panel stays empty without one.
     */
    private function drawPoster(Imagick $card, ?Imagick $poster): void
    {
        if (!$poster instanceof Imagick) {
            return;
        }

        $poster->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        $poster->cropThumbnailImage(308, 150);
        $poster->setImagePage(0, 0, 0, 0);
        $poster->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $mask = new Imagick();
        $mask->newImage(308, 150, new ImagickPixel('transparent'));
        $shape = new ImagickDraw();
        $shape->setFillColor(new ImagickPixel('white'));
        $shape->roundRectangle(0, 0, 307, 149, 12, 12);
        $mask->drawImage($shape);
        $poster->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $card->compositeImage($poster, Imagick::COMPOSITE_OVER, 804, 156);
        $mask->clear();
        $poster->clear();
    }

    /**
     * A line centred on $center, smaller until it fits $maxWidth.
     */
    private function centered(Imagick $card, string $text, string $weight, int $size, string $color, int $center, float $baseline, int $maxWidth): void
    {
        do {
            $font = $this->font($weight, $size, $color);
            $width = $this->width($card, $font, $text);
        } while ($width > $maxWidth && --$size >= 12);

        $card->annotateImage($font, $center - min($width, $maxWidth) / 2, $baseline, 0, $this->fit($card, $font, $text, $maxWidth));
    }

    /**
     * The text, cut with an ellipsis when it is wider than $max.
     */
    private function fit(Imagick $card, ImagickDraw $font, string $text, float $max): string
    {
        if ($this->width($card, $font, $text) <= $max) {
            return $text;
        }
        while (mb_strlen($text) > 1 && $this->width($card, $font, $text . '…') > $max) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text, ' ,') . '…';
    }

    private function font(string $weight, int $size, string $color, float $tracking = 0): ImagickDraw
    {
        $font = new ImagickDraw();
        $font->setFont(\sprintf('%s/assets/og/fonts/Epilogue-%s.ttf', $this->projectDir, $weight));
        $font->setTextEncoding('UTF-8');
        $font->setFontSize($size);
        $font->setFillColor(new ImagickPixel($color));
        $font->setTextAntialias(true);
        if (0.0 !== $tracking) {
            $font->setTextKerning($size * $tracking);
        }

        return $font;
    }

    private function width(Imagick $card, ImagickDraw $font, string $text): float
    {
        return $card->queryFontMetrics($font, $text)['textWidth'];
    }

    /**
     * Balanced lines (CSS text-wrap: balance): the fewest lines that fit $max, then the narrowest width that still
     * gives that many, so the last line is not a lone word.
     *
     * @return list<string>
     */
    private function wrap(Imagick $card, ImagickDraw $font, string $text, float $max): array
    {
        $words = explode(' ', $text);
        $greedy = function (float $width) use ($card, $font, $words): array {
            $lines = [];
            $line = '';
            foreach ($words as $word) {
                $try = '' === $line ? $word : $line . ' ' . $word;
                if ('' !== $line && $this->width($card, $font, $try) > $width) {
                    $lines[] = $line;
                    $try = $word;
                }
                $line = $try;
            }
            $lines[] = $line;

            return $lines;
        };

        $count = \count($greedy($max));
        [$low, $high] = [$max / 2, $max];
        while ($high - $low > 4) {
            $middle = ($low + $high) / 2;
            if (\count($greedy($middle)) > $count) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return $greedy($high);
    }
}
