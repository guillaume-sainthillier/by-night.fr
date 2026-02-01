<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Service\ResetInterface;

final class TemporaryFilesManager implements ResetInterface
{
    /**
     * @var string[]
     */
    private array $temporaryFiles = [];

    public function __construct()
    {
        register_shutdown_function([$this, 'removeTemporaryFiles']);
    }

    public function __destruct()
    {
        $this->removeTemporaryFiles();
    }

    public function reset(): void
    {
        $this->removeTemporaryFiles();
    }

    public function create(?string $suffix = null): string
    {
        $fs = new Filesystem();
        $tmpfilePath = $fs->tempnam(
            sys_get_temp_dir(),
            'by_night_',
            $suffix ?? ''
        );

        $this->temporaryFiles[] = $tmpfilePath;

        return $tmpfilePath;
    }

    /**
     * Removes all temporary files.
     */
    public function removeTemporaryFiles(): void
    {
        foreach ($this->temporaryFiles as $i => $path) {
            if (file_exists($path)) {
                unlink($path);
            }

            unset($this->temporaryFiles[$i]);
        }
    }
}
