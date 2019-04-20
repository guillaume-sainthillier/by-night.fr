<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 23/11/2016
 * Time: 20:52.
 */

namespace App\Command;

use App\Cleaner\ImageCleaner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppImagesCleanCommand extends AppCommand
{
    private $imageCleaner;

    /**
     * {@inheritdoc}
     */
    public function __construct(ImageCleaner $imageCleaner)
    {
        $this->imageCleaner = $imageCleaner;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:images:clean')
            ->setDescription('Supprime les images inutilisées sur le serveur')
            ->addOption('dry', 'dry-run', InputOption::VALUE_NONE, 'Active le monitor des fonctions');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->imageCleaner->clean($input->getOption('dry'));
    }
}
