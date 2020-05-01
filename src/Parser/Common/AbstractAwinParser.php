<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use XMLReader;
use SimpleXMLElement;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use ForceUTF8\Encoding;
use GuzzleHttp\Client;
use function GuzzleHttp\Psr7\copy_to_string;
use Psr\Log\LoggerInterface;

abstract class AbstractAwinParser extends AbstractParser
{
    /** @var string */
    private $awinApiKey;

    /** @var string */
    private $tempPath;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, string $tempPath, string $awinApiKey)
    {
        parent::__construct($logger, $eventProducer);
        $this->tempPath = $tempPath;
        $this->awinApiKey = $awinApiKey;
    }

    abstract protected function getAwinUrl(): string;

    abstract protected function getInfoEvents(array $datas): array;

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
            $event = $this->getInfoEvents($event);
            if (\count($event) > 0) {
                $this->publish($event);
            }

            $xml->next('product');
            unset($event);
        }
    }

    private function elementToArray(SimpleXMLElement $element): array
    {
        $array = [];
        foreach ($element->children() as $node) {
            $array[$node->getName()] = \is_array($node) ? $this->elementToArray($node) : Encoding::toUTF8(utf8_decode($node));
        }

        return $array;
    }

    private function downloadFile(string $url)
    {
        $client = new Client();
        $response = $client->request('GET', $url);

        $filePath = $this->tempPath . \DIRECTORY_SEPARATOR . sprintf('%s.gz', md5($url));
        file_put_contents($filePath, copy_to_string($response->getBody()));

        return $filePath;
    }
}
