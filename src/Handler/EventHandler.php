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
use App\Manager\TemporaryFilesManager;
use App\Utils\Cleaner;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vich\UploaderBundle\Handler\UploadHandler;

final readonly class EventHandler
{
    private const int MAX_CONCURRENT_REQUESTS = 50;

    public function __construct(
        private Cleaner $cleaner,
        private LoggerInterface $logger,
        private HttpClientInterface $client,
        private TemporaryFilesManager $temporaryFilesManager,
        private UploadHandler $uploadHandler,
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

        // Resolve redirects to group events by their final URL
        $eventsPerFinalUrl = $this->resolveRedirectsAndGroupByFinalUrl($eventsPerUrl);

        foreach (array_chunk(array_keys($eventsPerFinalUrl), self::MAX_CONCURRENT_REQUESTS) as $imageUrls) {
            $responses = [];
            /** @var array<string, string> $tempFilePaths */
            $tempFilePaths = [];

            foreach ($imageUrls as $imageUrl) {
                $response = $this->client->request('GET', $imageUrl, [
                    'user_data' => $imageUrl,
                ]);
                $responses[] = $response;
            }

            foreach ($this->client->stream($responses) as $response => $chunk) {
                $imageUrl = $response->getInfo('user_data');
                $currentEvents = $eventsPerFinalUrl[$imageUrl] ?? [];

                try {
                    if ($chunk->isTimeout()) {
                        $response->cancel();
                        unset($tempFilePaths[$imageUrl]);
                    } elseif ($chunk->isFirst()) {
                        if (200 !== $response->getStatusCode()) {
                            $response->cancel();
                        } else {
                            // Create temporary file for streaming
                            $tempFilePaths[$imageUrl] = $this->temporaryFilesManager->create();
                        }
                    } elseif (isset($tempFilePaths[$imageUrl])) {
                        // Write chunk to temporary file
                        file_put_contents($tempFilePaths[$imageUrl], $chunk->getContent(), \FILE_APPEND);

                        if ($chunk->isLast()) {
                            foreach ($currentEvents as $event) {
                                $this->uploadFile($event, $tempFilePaths[$imageUrl]);
                            }
                            unset($tempFilePaths[$imageUrl]);
                        }
                    }
                } catch (TransportExceptionInterface|HttpExceptionInterface|UnsupportedFileException $e) {
                    unset($tempFilePaths[$imageUrl]);

                    if ($e instanceof HttpExceptionInterface && 403 === $e->getResponse()->getStatusCode()) {
                        $this->logger->info(\sprintf('Url %s is not allowed', $imageUrl));
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

    /**
     * Resolve redirects using HEAD requests and group events by their final URL.
     * This avoids downloading the same content multiple times when different URLs redirect to the same destination.
     *
     * @param array<string, list<Event>> $eventsPerUrl
     *
     * @return array<string, list<Event>>
     */
    private function resolveRedirectsAndGroupByFinalUrl(array $eventsPerUrl): array
    {
        $eventsPerFinalUrl = [];
        $urlsToResolve = array_keys($eventsPerUrl);

        foreach (array_chunk($urlsToResolve, self::MAX_CONCURRENT_REQUESTS) as $urls) {
            $responses = [];
            foreach ($urls as $url) {
                $response = $this->client->request('HEAD', $url, [
                    'user_data' => $url,
                    'max_redirects' => 10,
                ]);
                $responses[] = $response;
            }

            foreach ($responses as $response) {
                $originalUrl = $response->getInfo('user_data');
                $events = $eventsPerUrl[$originalUrl];

                try {
                    // getStatusCode() waits for headers and follows redirects
                    $statusCode = $response->getStatusCode();
                    if (200 !== $statusCode) {
                        // Keep original URL for GET request, which will handle the error
                        $eventsPerFinalUrl[$originalUrl] = array_merge($eventsPerFinalUrl[$originalUrl] ?? [], $events);
                        continue;
                    }

                    // Get the final URL after all redirects
                    $finalUrl = $response->getInfo('url');
                    $eventsPerFinalUrl[$finalUrl] = array_merge($eventsPerFinalUrl[$finalUrl] ?? [], $events);
                } catch (TransportExceptionInterface|HttpExceptionInterface) {
                    // On error, keep original URL for GET request which will handle/log the error
                    $eventsPerFinalUrl[$originalUrl] = array_merge($eventsPerFinalUrl[$originalUrl] ?? [], $events);
                }
            }
        }

        return $eventsPerFinalUrl;
    }

    public function reset(): void
    {
        $this->temporaryFilesManager->reset();
    }

    /**
     * @throws UnsupportedFileException
     */
    private function uploadFile(Event $event, string $tempFilePath): void
    {
        $fileSize = filesize($tempFilePath);
        if (false === $fileSize || 0 === $fileSize) {
            $event->setImageSystemHash(null);
            $event->setImageSystemFile();

            return;
        }

        $hash = md5_file($tempFilePath);
        if ($event->getImageSystemHash() && $hash === $event->getImageSystemHash()) {
            return;
        }

        $mimeTypes = new MimeTypes();
        $contentType = $mimeTypes->guessMimeType($tempFilePath);
        $ext = match ($contentType) {
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/jpg',
            'image/jpeg' => 'jpeg',
            default => throw new UnsupportedFileException(\sprintf('Unable to find extension for mime type %s', $contentType)),
        };

        $tempFileBasename = ($event->getId() ?? uniqid());
        $pathUrl = parse_url((string) $event->getUrl(), \PHP_URL_PATH);
        $originalName = pathinfo($pathUrl, \PATHINFO_BASENAME) ?: ($tempFileBasename . '.' . $ext);

        $file = new UploadedFile($tempFilePath, $originalName, $contentType, test: true);

        $event->setImageHash($hash);
        $event->setImageSystemFile($file);

        // We do a manual upload on new entity because preFlush is after prePersist event
        if (null === $event->getId()) {
            $this->uploadHandler->upload($event, 'imageSystemFile');
        }
    }
}
