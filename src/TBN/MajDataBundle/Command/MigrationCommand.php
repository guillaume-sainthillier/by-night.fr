<?php

namespace TBN\MajDataBundle\Command;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Ville;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\PlaceRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class MigrationCommand extends EventCommand {

    protected $container;

    protected function configure() {
        $this
                ->setName('events:migrate')
                ->setDescription('Migration des lieux des événements');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        //Récupérations des dépendances
        $this->container = $this->getContainer();
        $em = $this->container->get("doctrine")->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        $repoPlaces = $em->getRepository("TBNAgendaBundle:Place");
        $repoVilles = $em->getRepository("TBNAgendaBundle:Ville");

        $this->writeln($output, "Parcours des événements...");
        $agendas = $repo->findBy(["place" => null]);
        $places = $repoPlaces->findAll();
        $villes = $repoVilles->findAll();
        foreach ($agendas as $agenda) {
            $place = $this->findPlaceBy($places, $villes, $agenda);

            $ville = $place->getVille();

            if ($ville) {
                $ville->setNom($ville->getNom() ? $ville->getNom() : $agenda->getVille())
                        ->setCodePostal($ville->getCodePostal() ? $ville->getCodePostal() : $agenda->getCodePostal());
            }

            /**
             * @var Agenda $agenda
             */
            $agenda->setPlace($place
                            ->setLatitude($place->getLatitude() ? $place->getLatitude() : $agenda->getLatitude())
                            ->setLongitude($place->getLongitude() ? $place->getLongitude() : $agenda->getLongitude())
                            ->setRue($place->getRue() ? $place->getRue() : $agenda->getRue())
                            ->setSite($place->getSite() ? $place->getSite() : $agenda->getSite())
            );

            $em->persist($agenda);
        }

        $em->flush();
        $this->writeln($output, "<info>" . count($agendas) . "</info> événements mis à jour");
        $this->writeln($output, "<info>" . count($places) . "</info> places mises à jour");
        $this->writeln($output, "<info>" . count($villes) . "</info> villes mises à jour");
    }

    private function cleanAddress($address) {
        return trim(mb_convert_case(preg_replace("/(\s+)/u", " ", $address), MB_CASE_TITLE, 'UTF-8'));
    }

    private function cleanVille($ville) {
        $villeSansTrait = str_replace("-", " ", $ville);
        return $this->cleanAddress(str_replace(["st ", "ST "], "saint ", $villeSansTrait));
    }

    private function cleanCodePostal($codePostal) {
        return trim(preg_replace("/\D/", "", $codePostal));
    }

    private function isSameVille(Ville $currentVille, $nomVille, $codePostalVille) {

        $currentNomVille = $this->sanitizeForSearch($currentVille->getNom());
        $currentCodePostalVille = $this->sanitizeForSearch($currentVille->getNom());
        $nomVille = $this->sanitizeForSearch($nomVille);
        $codePostalVille = $this->sanitizeForSearch($codePostalVille);

        return (($codePostalVille !== "" && $nomVille !== "" && $codePostalVille === $currentCodePostalVille && $nomVille === $currentNomVille) ||
            ($codePostalVille === "" && $nomVille === $currentNomVille) || ( $nomVille === "" && $codePostalVille === $currentCodePostalVille));
    }

    private function replaceAccents($string) {
        return str_replace(array('à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y'), $string);
    }

    private function sanitizeForSearch($string) {
        return $this->cleanAddress(preg_replace("/[^A-Za-z0-9 ]/u", "", $this->replaceAccents($string)));
    }

    private function isSamePlace(Site $site, Place $currentPlace, $nomPlace, $ruePlace, $villePlace, $latitudePlace, $longitudePlace) {
        if ($currentPlace->getSite() === $site) {
            $nomPlace = $this->sanitizeForSearch($nomPlace);
            $ruePlace = $this->sanitizeForSearch($ruePlace);
            $villePlace = $this->sanitizeForSearch($villePlace);
            $currentNomPlace = $this->sanitizeForSearch($currentPlace->getNom());
            $currentRuePlace = $this->sanitizeForSearch($currentPlace->getRue());
            $currentVillePlace = $this->sanitizeForSearch($currentPlace->getVille() ? $currentPlace->getVille()->getNom() : null);

            
            if (($nomPlace == $currentNomPlace) ||
                ($ruePlace != "" && $villePlace != "" && $ruePlace == $currentRuePlace && $villePlace == $currentVillePlace))
                //($latitudePlace !== "" && $longitudePlace !== "" && $latitudePlace == $currentPlace->getLatitude() && $longitudePlace == $currentPlace->getLongitude()))
            {
                return true;
            }
        }

        return false;
    }

    protected function findVilleBy(& $villes, $nomVille, $codePostal) {
        foreach ($villes as $ville) {
            if (($codePostal !== "" && $nomVille !== "" && $codePostal === $ville->getCodePostal() && $nomVille === $ville->getNom()) || ( $codePostal === "" && $nomVille === $ville->getNom()) || ( $nomVille === "" && $codePostal === $ville->getCodePostal())) {
                return $ville;
            }
        }

        if ($nomVille == null || trim($nomVille) == "" || (strlen($codePostal) > 0 && strlen($codePostal) < 5)) {
            return null;
        }


        $ville = (new Ville)->setNom($nomVille)->setCodePostal($codePostal);
        $villes[] = $ville;

        return $ville;
    }

    protected function findPlaceBy(& $places, & $villes, Agenda $agenda) {
        $rue = $this->cleanAddress($agenda->getRue());
        $nom = $this->cleanAddress($agenda->getLieuNom());
        $nomVille = $this->cleanVille($agenda->getVille());
        $codePostal = $this->cleanCodePostal($agenda->getCodePostal());

        if ($nom === "" || $nom === null) {
            $nom = $rue;
        }
        foreach ($places as $place) {
            if ($this->isSamePlace($agenda->getSite(), $place, $nom, $rue, $nomVille, $agenda->getLatitude(), $agenda->getLongitude())) {
                return $place;
            }

//            if($place->getSite() === $agenda->getSite())
//            {
//                if(($nom != "" && $nom == $place->getNom()) or
//                    ($nom === "" && $rue != "" && $nomVille != "" && $rue == $place->getRue() && $nomVille == $place->getVille()->getNom()))
//                {
//                    return $place;
//                }
//            }
        }

        $ville = $this->findVilleBy($villes, $nomVille, $codePostal);

        $place = (new Place)
                ->setRue($rue)
                ->setNom($nom)
                ->setVille($ville);
        $places[] = $place;

        return $place;
    }

}
