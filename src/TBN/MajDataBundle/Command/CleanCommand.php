<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 23/11/2016
 * Time: 20:52
 */

namespace TBN\MajDataBundle\Command;



use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:events:clean')
            ->setDescription('Mettre à jour les images sur le serveur')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cleaner = $this->getContainer()->get('tbn.image_cleaner');
        $cleaner->clean();
    }
}