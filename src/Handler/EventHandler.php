<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Exception\UnsupportedFileException;
use App\File\DeletableFile;
use App\Manager\TemporyFilesManager;
use App\Utils\Cleaner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vich\UploaderBundle\Handler\UploadHandler;

class EventHandler
{
    /**
     * @var int
     */
    private const MAX_CONCURRENT_REQUESTS = 50;

    public function __construct(
        private readonly Cleaner $cleaner,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $client,
        private readonly TemporyFilesManager $temporyFilesManager,
        private readonly UploadHandler $uploadHandler,
    ) {
    }

    public function cleanEvent(EventDto $dto): void
    {
        $this->cleaner->cleanEvent($dto);
        if (null !== $dto->place) {
            $this->cleaner->cleanPlace($dto->place);
            if (null !== $dto->place->city) {
                $this->cleaner->cleanCity($dto->place->city);
            }
        }
    }

    /**
     * @param iterable|array<Event> $events
     */
    public function handleDownloads(iterable $events): void
    {
        $eventsPerUrl = [];
        foreach ($events as $event) {
            if (!$event->getUrl()) {
                continue;
            }

            if (!str_starts_with((string) $event->getUrl(), 'http://') && !str_starts_with((string) $event->getUrl(), 'https://')) {
                continue;
            }

            $eventsPerUrl[$event->getUrl()][] = $event;
        }

        foreach (array_chunk(array_keys($eventsPerUrl), self::MAX_CONCURRENT_REQUESTS) as $imageUrls) {
            $responses = [];
            foreach ($imageUrls as $imageUrl) {
                $response = $this->client->request('GET', $imageUrl, [
                    'user_data' => $imageUrl,
                ]);
                $responses[] = $response;
            }

            foreach ($this->client->stream($responses) as $response => $chunk) {
                $imageUrl = $response->getInfo('user_data');
                $currentEvents = $eventsPerUrl[$imageUrl] ?? [];

                try {
                    if ($chunk->isTimeout()) {
                        $response->cancel();
                    } elseif ($chunk->isFirst() && 200 !== $response->getStatusCode()) {
                        $response->cancel();
                    } elseif ($chunk->isLast()) {
                        foreach ($currentEvents as $event) {
                            $this->uploadFile($event, $response->getContent());
                        }
                    }
                } catch (TransportExceptionInterface|HttpExceptionInterface|UnsupportedFileException $e) {
                    if ($e instanceof HttpExceptionInterface && 403 === $e->getResponse()->getStatusCode()) {
                        $this->logger->info(sprintf('Url %s is not allowed', $imageUrl));
                        continue;
                    }

                    $this->logger->error($e->getMessage(), [
                        'exception' => $e,
                        'extra' => [
                            'event' => [
                                'id' => array_map(static fn (Event $event) => $event->getId(), $currentEvents),
                                'url' => $imageUrl,
                            ],
                        ],
                    ]);
                }
            }
        }
    }

    public function reset(): void
    {
        $this->temporyFilesManager->reset();
    }

    /**
     * @throws UnsupportedFileException
     */
    private function uploadFile(Event $event, string $content): void
    {
        $hash = null;
        if ($content && $event->getImageSystemHash() && ($hash = md5($content)) === $event->getImageSystemHash()) {
            return;
        }

        $tempFileBasename = ($event->getId() ?? uniqid());
        $tempFilePath = $this->temporyFilesManager->create();
        $octets = file_put_contents($tempFilePath, $content);

        if (0 === $octets) {
            $event->setImageSystemHash(null);
            $event->setImageSystemFile(null);

            return;
        }

        $mimeTypes = new MimeTypes();
        $contentType = $mimeTypes->guessMimeType($tempFilePath);
        $ext = match ($contentType) {
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/jpg',
            'image/jpeg' => 'jpeg',
            default => throw new UnsupportedFileException(sprintf('Unable to find extension for mime type %s', $contentType)),
        };

        $pathUrl = parse_url($event->getUrl(), \PHP_URL_PATH);
        $originalName = pathinfo($pathUrl, \PATHINFO_BASENAME) ?: ($tempFileBasename . '.' . $ext);
        $file = new DeletableFile($tempFilePath, $originalName, $contentType, null, true);
        $event->setImageHash($hash);
        $event->setImageSystemFile($file);

        // We do a manual upload on new entity because preFlush is after prePersist event
        if (null === $event->getId()) {
            $this->uploadHandler->upload($event, 'imageSystemFile');
        }
    }
}
