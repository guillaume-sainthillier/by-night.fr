<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Pager;

use Symfony\Contracts\Cache\CacheInterface;

/**
 * The id a populate counts its pages down from (see IdRangePager), kept in Redis so that the
 * populate command and every elastica worker handling its pages share it. Were each process to
 * read the highest id on its own, the events created during the populate would push the blocks
 * of the later pages up, and the oldest block would never be indexed.
 */
final readonly class IdRangeCeilings
{
    public function __construct(
        private CacheInterface $memoryCache,
    ) {
    }

    /**
     * @param callable(): int $highestId called when the index has no ceiling yet
     */
    public function get(string $indexName, callable $highestId): int
    {
        return $this->memoryCache->get($this->getKey($indexName), static fn (): int => $highestId());
    }

    public function forget(string $indexName): void
    {
        $this->memoryCache->delete($this->getKey($indexName));
    }

    private function getKey(string $indexName): string
    {
        return \sprintf('elastica.populate.id_ceiling.%s', $indexName);
    }
}
