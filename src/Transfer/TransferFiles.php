<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer;

use App\Enum\TransferFormat;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The files of the members' imports and exports, in transfers.storage (private). The spreadsheet library reads and
 * writes local files: a worker takes a copy out, works on it, and puts the result back.
 */
final readonly class TransferFiles
{
    public function __construct(
        #[Target('transfers.storage')]
        private FilesystemOperator $storage,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * A new, empty local file, removed by remove().
     */
    public function createLocalFile(TransferFormat $format): string
    {
        $path = $this->filesystem->tempnam(sys_get_temp_dir(), 'by-night-transfer-', '.' . $format->value);

        return $path;
    }

    /**
     * A local copy of a stored file.
     */
    public function download(string $path, TransferFormat $format): string
    {
        $local = $this->createLocalFile($format);
        $stream = $this->storage->readStream($path);
        $target = fopen($local, 'w') ?: throw new RuntimeException(\sprintf('Unable to write "%s".', $local));

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $local;
    }

    /**
     * Stores a local file (it stays where it is).
     */
    public function upload(string $local, string $path): void
    {
        $stream = fopen($local, 'r') ?: throw new RuntimeException(\sprintf('Unable to read "%s".', $local));

        try {
            $this->storage->writeStream($path, $stream);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @return resource the stored file, for a download
     */
    public function readStream(string $path)
    {
        return $this->storage->readStream($path);
    }

    public function exists(?string $path): bool
    {
        return null !== $path && $this->storage->fileExists($path);
    }

    public function delete(?string $path): void
    {
        if (null !== $path) {
            $this->storage->delete($path);
        }
    }

    public function remove(string ...$locals): void
    {
        $this->filesystem->remove($locals);
    }
}
