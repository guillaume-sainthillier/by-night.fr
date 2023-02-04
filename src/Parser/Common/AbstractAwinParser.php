<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Dto\EventDto;
use App\Handler\EventHandler;
use App\Handler\ReservationsHandler;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use ForceUTF8\Encoding;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use XMLReader;

abstract class AbstractAwinParser extends AbstractParser
{
    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        ReservationsHandler $reservationsHandler,
        protected HttpClientInterface $httpClient,
        private string $tempPath,
        private string $awinApiKey
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler, $reservationsHandler);
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $path = $this->downloadFile(str_replace('%key%', $this->awinApiKey, $this->getAwinUrl()));
        $xml = new XMLReader();
        $xml->open('compress.zlib://' . $path);

        do {
            $xml->read();
        } while ('product' !== $xml->name);

        while ('product' === $xml->name) {
            $event = $this->elementToArray(new SimpleXMLElement($xml->readOuterXML()));
            $event = $this->arrayToDto($event);
            if (null !== $event) {
                $this->publish($event);
            }

            $xml->next('product');
            unset($event);
        }
    }

    private function downloadFile(string $url): string
    {
        $response = $this->httpClient->request('GET', $url);

        $filePath = $this->tempPath . \DIRECTORY_SEPARATOR . sprintf('%s.gz', md5($url));
        $fileHandler = fopen($filePath, 'w');
        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }

        return $filePath;
    }

    abstract protected function getAwinUrl(): string;

    private function elementToArray(SimpleXMLElement $element): array
    {
        $array = [];
        foreach ($element->children() as $node) {
            $array[$node->getName()] = \is_array($node) ? $this->elementToArray($node) : Encoding::toUTF8(utf8_decode($node));
        }

        return $array;
    }

    abstract protected function arrayToDto(array $data): ?EventDto;
}
