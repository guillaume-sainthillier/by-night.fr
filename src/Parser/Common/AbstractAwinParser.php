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
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractAwinParser extends AbstractParser
{
    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        protected HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%/var/storage/temp')]
        private readonly string $tempPath,
        #[Autowire(env: 'AWIN_API_KEY')]
        private readonly string $awinApiKey,
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler);
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $path = $this->downloadFile(str_replace('%key%', $this->awinApiKey, $this->getAwinUrl()));
        $handle = gzopen($path, 'r');

        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Unable to open gzipped file: %s', $path));
        }

        try {
            $headers = fgetcsv($handle);
            if (false === $headers) {
                throw new \RuntimeException('Unable to read CSV headers');
            }

            while (false !== ($row = fgetcsv($handle))) {
                if (\count($row) !== \count($headers)) {
                    continue;
                }

                $data = array_combine($headers, $row);
                $event = $this->arrayToDto($data);
                if (null !== $event) {
                    $this->publish($event);
                }

                unset($data, $event);
            }
        } finally {
            gzclose($handle);
        }
    }

    private function downloadFile(string $url): string
    {
        $response = $this->httpClient->request('GET', $url);

        $filePath = $this->tempPath . \DIRECTORY_SEPARATOR . \sprintf('%s.gz', md5($url));
        $fileHandler = fopen($filePath, 'w');
        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

        return $filePath;
    }

    abstract protected function getAwinUrl(): string;

    abstract protected function arrayToDto(array $data): ?EventDto;
}
