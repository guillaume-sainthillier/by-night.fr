<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class AppImagesMigrateCommand extends AppCommand
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var PaginatorInterface */
    private $paginator;

    /** @var string */
    private $webDir;

    /** @var OutputInterface */
    private $output;

    /** @var Filesystem */
    private $fs;

    /**
     * {@inheritdoc}
     */
    public function __construct(EntityManagerInterface $entityManager, PaginatorInterface $paginator, string $webDir)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->paginator = $paginator;
        $this->webDir = $webDir;
        $this->fs = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:images:migrate');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $uploadDirectory = $this->webDir . DIRECTORY_SEPARATOR . 'uploads';

        $query = $this->entityManager
            ->createQuery('SELECT a.path, a.systemPath, a.createdAt FROM App:Event a WHERE a.path IS NOT NULL OR a.systemPath IS NOT NULL');

        $events = $this->paginator->paginate(
            $query,
            1,
            1000
        );

        for ($i = 0; $i < ceil($events->getTotalItemCount() / $events->getItemNumberPerPage()); $i++) {
            foreach ($events->getItems() as $event) {
                if ($event['path']) {
                    $this->move($uploadDirectory . DIRECTORY_SEPARATOR . 'documents', $event['path'], $event['createdAt']);
                }

                if ($event['systemPath']) {
                    $this->move($uploadDirectory . DIRECTORY_SEPARATOR . 'documents', $event['systemPath'], $event['createdAt']);
                }
            }
            $events->setCurrentPageNumber($i + 2);
        }
    }

    private function move(string $path, string $filename, \DateTimeInterface $createdAt)
    {
        $oldDirectory = $path . DIRECTORY_SEPARATOR . substr($filename, 0, 3) . DIRECTORY_SEPARATOR . substr($filename, 3, 3) . DIRECTORY_SEPARATOR . substr($filename, 6, 3);
        $oldFile = $oldDirectory . DIRECTORY_SEPARATOR . $filename;

        $newDirectory = $path . DIRECTORY_SEPARATOR . $createdAt->format('Y') . DIRECTORY_SEPARATOR . $createdAt->format('m') . DIRECTORY_SEPARATOR . $createdAt->format('d');
        $newFile = $newDirectory . DIRECTORY_SEPARATOR . $filename;

        $this->output->write(sprintf('Moving <info>%s</info> to <info>%s</info>... ', $oldFile, $newFile), false, OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE);

        if (!$this->fs->exists($oldFile)) {
            if (!$this->output->isVerbose()) {
                $this->output->writeln(sprintf('<error>%s</error>', $oldFile));
            }
            $this->output->writeln("<error>Not exists</error>", OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE);
            return;
        }

        $this->fs->mkdir($newDirectory);
        $this->fs->rename($oldFile, $newFile);

        $this->output->writeln("<success>OK</success>", OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE);
    }
}
