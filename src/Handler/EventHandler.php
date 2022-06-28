<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Exception\UnsupportedFileException;
use App\File\DeletableFile;
use App\Utils\Cleaner;
use const DIRECTORY_SEPARATOR;
use const PATHINFO_BASENAME;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventHandler
{
    private HttpClientInterface $client;

    public function __construct(private Cleaner $cleaner, private LoggerInterface $logger, private string $tempPath)
    {
        $this->client = HttpClient::create();
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

    public function handleDownload(Event $event): void
    {
        $url = $event->getUrl();
        try {
            $response = $this->client->request('GET', $url);
            $content = $response->getContent();
            $this->uploadFile($event, $content);
        } catch (TransportExceptionInterface|HttpExceptionInterface|UnsupportedFileException $e) {
            if ($e instanceof HttpExceptionInterface && 403 === $e->getResponse()->getStatusCode()) {
                return;
            }

            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => [
                    'event' => [
                        'id' => $event->getId(),
                        'url' => $url,
                    ],
                ],
            ]);
        }
    }

    /**
     * @throws UnsupportedFileException
     */
    private function uploadFile(Event $event, string $content): void
    {
        $tempFileBasename = ($event->getId() ?: uniqid());
        $tempFilePath = $this->tempPath . DIRECTORY_SEPARATOR . $tempFileBasename;
        $octets = file_put_contents($tempFilePath, $content);
        if (0 === $octets) {
            unlink($tempFilePath);
            $event->setImageSystemFile(null);

            return;
        }

        $mimeTypes = new MimeTypes();
        $contentType = $mimeTypes->guessMimeType($tempFilePath);
        $ext = match ($contentType) {
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/jpg', 'image/jpeg' => 'jpeg',
            default => throw new UnsupportedFileException(sprintf('Unable to find extension for mime type %s', $contentType)),
        };

        $originalName = pathinfo($event->getUrl(), PATHINFO_BASENAME) ?: ($tempFileBasename . '.' . $ext);
        $file = new DeletableFile($tempFilePath, $originalName, $contentType, null, true);
        $event->setImageSystemFile($file);
    }
}
