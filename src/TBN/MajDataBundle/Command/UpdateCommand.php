<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use TBN\AgendaBundle\Entity\Agenda;

use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Entity\HistoriqueMaj;
use TBN\MajDataBundle\Utils\DoctrineEventHandler;
use TBN\MajDataBundle\Utils\Monitor;


/**
 * UpdateCommand gère la commande liée à l'aggregation d'événements
 *
 * @author guillaume
 */
class UpdateCommand extends EventCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected function configure()
    {
        $this
            ->setName('events:update')
            ->setDescription('Mettre à jour les événements sur By Night')
            ->addArgument('site', InputArgument::REQUIRED, 'Quel site voulez-vous mettre à jour ?')
            ->addArgument('parser', InputArgument::OPTIONAL, 'Si défini, le nom du parser à lancer, sinon tous les parsers disponibles');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            //Début de construction de l'historique de la MAJ
            $historique = new HistoriqueMaj();

            //Récupération des arguments / options
            $subdomainSite = $input->getArgument('site');
            $parserName = $input->getArgument('parser');
            $env = $input->getOption('env');

            //Récupérations des dépendances
            $this->container = $this->getContainer();
            $parserManager = $this->container->get('parser_manager');
            $em = $this->container->get('doctrine')->getManager();
            /**
             * @var DoctrineEventHandler $doctrineHandler
             */
            $doctrineHandler = $this->container->get('tbn.doctrine_event_handler');

            //Récupération des repos
            $em->getConnection()->getConfiguration()->setSQLLogger(null);
            $repoSite = $em->getRepository('TBNMainBundle:Site');
            $repoSiteInfo = $em->getRepository('TBNUserBundle:SiteInfo');

            //Récupération du site demandé par l'user
            $site = $repoSite->findOneBy(['subdomain' => $subdomainSite]);
            $siteInfo = $repoSiteInfo->findOneBy([]);

            Monitor::$output = $output;
            Monitor::$log = false;

            if ($site === null) {
                throw new RuntimeException(sprintf('<error>Le site %s demandé est introuvable en base</error>', $subdomainSite));
            }

            //Chargement des explorations existantes en base
            $doctrineHandler->init($site, true);

            //Définition des parsers disponibles pour chaque site
            $parsers = [
                'toulouse' => [
                    'toulouse',
                    'tourisme',
                    'bikini',
                    'soonnight',
                    'facebook',
                ],
                'paris' => [
                    'tourisme',
                    'soonnight',
                    'facebook'
                ],
                'montpellier' => ['soonnight', 'facebook'],
                'lyon' => ['soonnight', 'facebook'],
                'lille' => ['soonnight', 'facebook'],
                'nice' => ['soonnight', 'facebook'],
                'nantes' => ['soonnight', 'facebook'],
                'marseille' => ['soonnight', 'facebook'],
                'bordeaux' => ['soonnight', 'facebook'],
                'brest' => ['soonnight', 'facebook'],
            ];

            $this->writeln($output, sprintf('<info>[%s]</info> Mise à jour de <info>%s By Night</info>...', $env, $site->getNom()));

            //Récupération des parsers
            foreach ($parsers[$subdomainSite] as $serviceName) {
                if (!$parserName || $serviceName === $parserName) {
                    $parser = $this->container->get('tbn.parser.' . $subdomainSite . '.' . $serviceName);

                    //Dépendances dynamiques liées aux différents parsers
                    $parser->setOutput($output)->setSite($site)->setSiteInfo($siteInfo);
                    $parserManager->add($parser);
                }
            }

            //Récupération des événements
            $agendas = Monitor::bench('Récupération Parser', function() use(&$parserManager, &$output) {
                return $parserManager->getAgendas($output);
            }, true);
//            $agendas = array_slice($agendas, 0, 100);

            $batchSize = 50;
            $size = ceil(count($agendas) / $batchSize);


            $progress = new ProgressBar($output, $size);
            $progress->start();
            foreach ($agendas as $i => $agenda) {
                if(! $agenda->getSite()) {
                    $agenda->setSite($site);
                }
                if($agenda->getPlace() && !$agenda->getPlace()->getSite()) {
                    $agenda->getPlace()->setSite($site);
                }

                $doctrineHandler->handleEvent($agenda, $env !== 'prod');
                if (($i % $batchSize) === $batchSize - 1) {
                    $doctrineHandler->flush();
                    $progress->advance();
                }
            }
            $doctrineHandler->flush();
            $progress->finish();
            $this->writeln($output, '');

            $stats = $this->displayStats($output, $doctrineHandler);
            $nbExplorations = $stats['nbExplorations'];
            $nbUpdate = $stats['nbUpdates'];
            $nbInsert = $stats['nbInserts'];
            $nbBlackList = $stats['nbBlacklists'];

            $historique
                ->setFromData($parserName ?: 'tous')
                ->setExplorations($nbBlackList + $nbExplorations)
                ->setNouvellesSoirees($nbInsert)
                ->setUpdateSoirees($nbUpdate)
                ->setSite($site);

            $em->merge($historique->setDateFin(new \DateTime));
            $em->flush();

        } catch (\Exception $e) {
            $this->writeln($output, $e->getTraceAsString());
            throw new \Exception('Erreur dans le traitement', 0, $e);
        }
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     *
     * @throws \LogicException
     */
    protected function getContainerBuilder()
    {
        if ($this->containerBuilder) {
            return $this->containerBuilder;
        }

        if (!is_file($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $this->containerBuilder = $container;
    }

    private function findServiceIdsContaining(ContainerBuilder $builder, $name)
    {
        $serviceIds = $builder->getServiceIds();
        $foundServiceIds = array();
        $name = strtolower($name);
        foreach ($serviceIds as $serviceId) {
            if (false === strpos($serviceId, $name)) {
                continue;
            }
            $foundServiceIds[] = $serviceId;
        }

        return $foundServiceIds;
    }
}
