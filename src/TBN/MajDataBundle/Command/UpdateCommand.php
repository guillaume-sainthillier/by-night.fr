<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use TBN\MajDataBundle\Parser\BikiniParser;
use TBN\MajDataBundle\Parser\DynamoParser;
use TBN\MajDataBundle\Parser\ToulouseParser;
use TBN\MajDataBundle\Parser\SoonNightParser;
use TBN\MajDataBundle\Parser\FaceBookParser;
use TBN\MajDataBundle\Parser\ToulouseTourismeParser;
use TBN\MajDataBundle\Parser\ParisTourismeParser;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\MajDataBundle\Entity\HistoriqueMaj;
use TBN\MainBundle\Entity\Site;
use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\ParserManager\ParserManager;
use TBN\SocialBundle\Social\Facebook;
use TBN\MajDataBundle\Entity\BlackListRepository;


/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class UpdateCommand extends EventCommand
{

    protected $container;
    
    protected function configure()
    {
        $this
            ->setName('events:update')
            ->setDescription('Mettre à jour les événements sur By Night')
            ->addArgument('site',       InputArgument::REQUIRED,    'Quel site voulez-vous mettre à jour ?')
            ->addArgument('parser',     InputArgument::OPTIONAL,    'Si définie, le parser à lancer, sinon tous les parsers disponibles')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        //Récupération des arguments / options
        $subdomainSite      = $input->getArgument('site');
        $parser             = $input->getArgument('parser');
        $env                = $input->getOption('env');

        //Récupérations des dépendances
        $this->container    = $this->getContainer();
        $parserManager      = $this->container->get("parser_manager");
        $em                 = $this->container->get("doctrine")->getManager();
        $geocoder           = $this->container->get('ivory_google_map.geocoder');
        $fbAPI              = $this->container->get("tbn.social.facebook");

        $repo               = $em->getRepository("TBNAgendaBundle:Agenda");
        $repoSite           = $em->getRepository("TBNMainBundle:Site");
        $repoBL             = $em->getRepository("TBNMajDataBundle:BlackList");
        $repoSiteInfo       = $em->getRepository("TBNUserBundle:SiteInfo");
	
        //Récupération du site demandé
        $site               = $repoSite->findOneBy(["subdomain" => $subdomainSite]);
        $siteInfo           = $repoSiteInfo->findOneBy([]);
        $getter             = "parse".lcfirst($subdomainSite);

        if($site === null)
        {
            $this->writeln($output, "<error>[".$env."] Le site ".$subdomainSite." demandé est introuvable</error>");
        }else
        {
            $this->writeln($output, "[".$env."] Mise à jour de <info>".$site->getNom()." By Night</info>...");
            $this->$getter($parserManager, $repo, $repoBL, $site, $parser, $siteInfo, $geocoder, $fbAPI);
            $this->persistAgendas($output, $em, $site, $parserManager, $parser, $env);
        }   
    }

    protected function parseParis(  ParserManager $parserManager, AgendaRepository $repo, BlackListRepository $repoBL,
                                    Site $currentSite, $site, SiteInfo $siteInfo, $geocoder, Facebook $fbAPI)
    {
        if(!$site or $site === "soonight")
        {
            $parserManager->addAgendaParser(new SoonNightParser($repo, $this->container->getParameter("url_soonnight_paris")));
        }

        if(!$site or $site === "tourisme")
        {
            $parserManager->addAgendaParser(new ParisTourismeParser($repo));
        }

	if(!$site or $site === "facebook")
        {
            $parserManager->addAgendaParser(new FaceBookParser($repo, $repoBL, $fbAPI, $currentSite, $siteInfo, $geocoder));
        }
    }

    protected function parseToulouse(   ParserManager $parserManager, AgendaRepository $repo, BlackListRepository $repoBL,
                                        Site $currentSite, $site, SiteInfo $siteInfo, $geocoder, Facebook $fbAPI)
    {
        if(!$site or $site === "toulouse")
        {
            $url = "http://data.grandtoulouse.fr/web/guest/les-donnees/-/opendata/card/21905-agenda-des-manifestations-culturelles/resource/document?p_p_state=exclusive&_5_WAR_opendataportlet_jspPage=%2Fsearch%2Fview_card_license.jsp";
            $parserManager->addAgendaParser(new ToulouseParser($repo, $url));
        }

        if(!$site or $site === "bikini")
        {
            $parserManager->addAgendaParser(new BikiniParser($repo, $this->container->getParameter("url_bikini")));
        }

        if(!$site or $site === "dynamo")
        {
            $parserManager->addAgendaParser(new DynamoParser($repo, $this->container->getParameter("url_dynamo")));
        }

        if(!$site or $site === "tourisme")
        {
            $parserManager->addAgendaParser(new ToulouseTourismeParser($repo));
        }

        if(!$site or $site === "soonight")
        {
            $parserManager->addAgendaParser(new SoonNightParser($repo, $this->container->getParameter("url_soonnight_tlse")));
        }

        if(!$site or $site === "facebook")
        {
            $parserManager->addAgendaParser(new FaceBookParser($repo, $repoBL, $fbAPI, $currentSite, $siteInfo, $geocoder));
        }
    }

    

    protected function persistAgendas(OutputInterface $output, $em, Site $site, ParserManager $parserManager, $parser, $env)
    {
        $full_agendas       = $parserManager->parse($output);

        $this->write($output, "Tri...");
        $agendas	    = $this->cleanEvents($full_agendas);
        $this->writeln($output, "<info>".(count($full_agendas) - count($agendas))."</info> doublons detectés");
        $blackLists         = $parserManager->getBlackLists();
        $nbNewSoirees	    = 0;
        $nbUpdateSoirees    = 0;

        $this->write($output, "Persistance...");
        foreach($agendas as $agenda)
        {
            if($agenda->getNom() !== null and trim($agenda->getNom()) !== "")
            {
                if($agenda->getId() === null)
                {
                    $nbNewSoirees++;
                }else
                {
                    $nbUpdateSoirees++;
                }

                if($env === "prod" and $agenda->getPath() === null and $agenda->getUrl() !== null) // On récupère l'image distante
                {
                    $this->downloadImage($agenda);
                }

                if($agenda->getSite() === null)
                {
                    $agenda->setSite($site);
                }

                $em->persist($agenda);
            }
        }

        foreach($blackLists as $blackList)
        {
            $em->persist($blackList);
        }

	$historique = new HistoriqueMaj();
	$historique->setFromData($parser ? $parser : 'tous')
		->setSite($site)
		->setNouvellesSoirees($nbNewSoirees)
		->setUpdateSoirees($nbUpdateSoirees)
                ->setBlackLists(count($blackLists));

        $em->persist($historique);
        $em->flush();
        $this->writeln($output, "<info>OK</info>");
    }

    public function downloadImage(Agenda $agenda)
    {
        try
        {
            $url = preg_replace('/([^:])(\/{2,})/', '$1/', $agenda->getUrl());
            $agenda->setUrl($url);
            //En cas d'url du type:  http://u.rl/image.png?params
            $ext = preg_replace("/\?(.+)/", "", pathinfo($url, PATHINFO_EXTENSION));

            $filename = sha1(uniqid(mt_rand(), true)).".".$ext;
            $result = file_get_contents($url);

            if($result !== false)
            {
                // Save it to disk
                $savePath = $agenda->getUploadRootDir()."/".$filename;
                $fp = fopen($savePath,'x');

                if($fp !== false)
                {
                    fwrite($fp, $result);
                    fclose($fp);
                }
            }

            $agenda->setPath($filename);
        }catch(\Exception $e)
        {
            $agenda->setPath(null);
            $this->get("logger")->error($e->getMessage());
        }
    }

    
}
