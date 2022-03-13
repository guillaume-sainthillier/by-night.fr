<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Entity\File;
use Vich\UploaderBundle\Storage\StorageInterface;

#[AsCommand(
    name: 'app:migrate:medias',
    description: 'Migrate medias',
)]
class MigrateMediasCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private StorageInterface $storage
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Users');
        $objects = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.imageSystem.dimensions IS NULL OR u.imageSystem.originalName IS NULL OR u.imageSystemHash IS NULL')
            ->orWhere('u.image.dimensions IS NULL OR u.image.originalName IS NULL OR u.imageHash IS NULL')
            ->getQuery()
            ->toIterable();

        /** @var User $object */
        foreach ($objects as $object) {
            $currentImage = $object->getImageSystem();
            if (!empty($currentImage->getName())) {
                if (empty($currentImage->getOriginalName())) {
                    $currentImage->setOriginalName($currentImage->getName());
                }

                // Download image
                if (empty($currentImage->getDimensions()) || empty($object->getImageSystemHash())) {
                    try {
                        $stream = $this->storage->resolveStream($object, 'imageSystemFile');
                        [
                            'mainColor' => $mainColor,
                            'checksum' => $checksum
                        ] = $this->inject($stream, $currentImage->getName(), $currentImage);

                        $object
                            ->setImageSystemHash($checksum)
                            ->setImageSystemMainColor($mainColor);
                    } catch (Exception $e) {
                        $this->logger->error($e->getMessage());
                    }
                }
            }

            $currentImage = $object->getImage();
            if (!empty($currentImage->getName())) {
                if (empty($currentImage->getOriginalName())) {
                    $currentImage->setOriginalName($currentImage->getName());
                }

                // Download image
                if (empty($currentImage->getDimensions()) || empty($object->getImageSystemHash())) {
                    try {
                        $stream = $this->storage->resolveStream($object, 'imageFile');
                        [
                            'mainColor' => $mainColor,
                            'checksum' => $checksum
                        ] = $this->inject($stream, $currentImage->getName(), $currentImage);

                        $object
                            ->setImageHash($checksum)
                            ->setImageMainColor($mainColor);
                    } catch (Exception $e) {
                        $this->logger->error($e->getMessage());
                    }
                }
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->info('DONE');

        return Command::SUCCESS;
    }

    private function inject($stream, string $name, File $file): array
    {
        $fs = new Filesystem();
        $tmp = tempnam(sys_get_temp_dir(), 'ByNight');

        try {
            $fs->dumpFile($tmp, $stream);
            $fakeFile = new UploadedFile(
                $tmp,
                $name,
                null,
                null,
                true
            );

            $file->setSize($fakeFile->getSize());
            $file->setMimeType($fakeFile->getMimeType());

            if (null !== $fakeFile->getMimeType() && str_contains($fakeFile->getMimeType(), 'image/') && 'image/svg+xml' !== $fakeFile->getMimeType() && false !== $dimensions = @getimagesize($tmp)) {
                $file->setDimensions(array_splice($dimensions, 0, 2));
            }

            return [
                'checksum' => md5_file($tmp),
            ];
        } finally {
            $fs->remove($tmp);
        }
    }
}
