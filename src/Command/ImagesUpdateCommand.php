<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Updater\UserUpdater;
use App\Utils\Monitor;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImagesUpdateCommand extends Command
{
    protected static $defaultName = 'app:images:update';

    public function __construct(private UserUpdater $userUpdater)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Mettre à jour les images (events, users) en provenance des réseaux sociaux')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Date de dernière mise à jour', 'monday this week');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = new DateTime($input->getOption('from'));

        Monitor::writeln('Mise à jour des images <info>utilisateur</info>');
        $this->userUpdater->update($from);

        return Command::SUCCESS;
    }
}