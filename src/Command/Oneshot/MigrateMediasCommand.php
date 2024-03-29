<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command\Oneshot;

use App\Entity\Event;
use App\Entity\User;
use App\Utils\PaginateTrait;
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
    use PaginateTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly StorageInterface $storage
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Users');

        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.imageSystem.name IS NOT NULL AND (u.imageSystem.dimensions IS NULL OR u.imageSystem.originalName IS NULL OR u.imageSystemHash IS NULL)')
            ->orWhere('u.image.name IS NOT NULL AND (u.image.dimensions IS NULL OR u.image.originalName IS NULL OR u.imageHash IS NULL)');

        $paginator = $this->createQueryBuilderPaginator($queryBuilder, 1, 500);
        for ($page = 1; $page <= $paginator->getNbPages(); ++$page) {
            $paginator->setCurrentPage($page);

            /** @var User $object */
            foreach ($paginator->getCurrentPageResults() as $object) {
                if (616 === $object->getId()) {
                    continue;
                }

                $this->logger->info(sprintf(
                    'Handling user "%d"',
                    $object->getId()
                ));

                $this->handle($object);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->info('Events');

        $queryBuilder = $this
            ->entityManager
            ->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->where('e.imageSystem.name IS NOT NULL AND (e.imageSystem.dimensions IS NULL OR e.imageSystem.originalName IS NULL OR e.imageSystemHash IS NULL)')
            ->orWhere('e.image.name IS NOT NULL AND (e.image.dimensions IS NULL OR e.image.originalName IS NULL OR e.imageHash IS NULL)');

        $paginator = $this->createQueryBuilderPaginator($queryBuilder, 1, 500);
        for ($page = 1; $page <= $paginator->getNbPages(); ++$page) {
            $paginator->setCurrentPage($page);

            /** @var Event $object */
            foreach ($paginator->getCurrentPageResults() as $object) {
                $this->logger->info(sprintf(
                    'Handling event "%d"',
                    $object->getId()
                ));

                $this->handle($object);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->info('DONE');

        return Command::SUCCESS;
    }

    private function handle(Event|User $object): void
    {
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
                        'checksum' => $checksum
                    ] = $this->inject($stream, $currentImage->getName(), $currentImage);

                    $object->setImageSystemHash($checksum);
                } catch (Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        }

        $currentImage = $object->getImage();
        if (!empty($currentImage->getName())) {
            if (empty($currentImage->getOriginalName())) {
                $currentImage->setOriginalName($currentImage->getName());
            }

            // Download image
            if (empty($currentImage->getDimensions()) || empty($object->getImageHash())) {
                try {
                    $stream = $this->storage->resolveStream($object, 'imageFile');
                    [
                        'checksum' => $checksum
                    ] = $this->inject($stream, $currentImage->getName(), $currentImage);

                    $object->setImageHash($checksum);
                } catch (Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        }
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

            if (
                null !== $fakeFile->getMimeType()
                && str_contains($fakeFile->getMimeType(), 'image/')
                && 'image/svg+xml' !== $fakeFile->getMimeType()
                && false !== $dimensions = @getimagesize($tmp)
            ) {
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
