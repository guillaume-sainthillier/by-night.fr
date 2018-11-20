<?php

namespace App\Command;

use App\Fetcher\EventFetcher;
use App\Parser\Common\FaceBookParser;
use App\Parser\ParserInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchCommand extends AppCommand
{
    /**
     * @var EventFetcher
     */
    private $eventFetcher;

    /**
     * @var ProducerInterface
     */
    private $eventProducer;

    /**
     * @var ProducerInterface
     */
    private $updateFbIdProducer;

    public function __construct(EventFetcher $eventFetcher, ProducerInterface $eventProducer, ProducerInterface $updateFbIdProducer)
    {
        $this->eventFetcher       = $eventFetcher;
        $this->eventProducer      = $eventProducer;
        $this->updateFbIdProducer = $updateFbIdProducer;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tbn:events:fetch')
            ->setDescription('Ajouter / mettre à jour des nouveaux événéments sur By Night')
            ->addArgument('parser', InputArgument::REQUIRED, 'Nom du service à executer');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parser = $input->getArgument('parser');
        if (!$this->getContainer()->has($parser)) {
            throw new \LogicException(\sprintf(
                'Le service "%s" est introuvable',
                $parser
            ));
        }

        $service = $this->getContainer()->get($parser);
        if (!$service instanceof ParserInterface) {
            throw new \LogicException(\sprintf(
                'Le service "%s" doit être une instance de ParserInterface',
                $service
            ));
        }

        $events  = $this->eventFetcher->fetchEvents($service);
        foreach ($events as $event) {
            $this->getContainer()->get('old_sound_rabbit_mq.add_event_producer')->publish(\serialize($event));
        }

        if ($service instanceof FaceBookParser) {
            foreach ($service->getIdsToMigrate() as $oldValue => $newValue) {
                $this->getContainer()->get('old_sound_rabbit_mq.update_fb_id_producer')->publish(\serialize(['old' => $oldValue, 'new' => $newValue]));
            }
        }
    }
}
