<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

final class TemporyFilesManager
{
    /**
     * @var resource[]
     */
    private array $temporaryResources = [];

    public function __construct()
    {
        register_shutdown_function([$this, 'reset']);
    }

    public function __destruct()
    {
        $this->reset();
    }

    public function create(): string
    {
        $tmpfile = tmpfile();

        // We need to store resource in order to avoid garbage collector closing pointer
        $this->temporaryResources[] = $tmpfile;

        return stream_get_meta_data($tmpfile)['uri'];
    }

    public function reset(): void
    {
        unset($this->temporaryResources);
        $this->temporaryResources = [];
    }
}
