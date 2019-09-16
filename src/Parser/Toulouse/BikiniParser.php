<?php

namespace App\Parser\Toulouse;

use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use App\Utils\Monitor;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Guillaume SAINTHILLIER
 */
class BikiniParser extends AbstractParser
{
    private const RSS_URL = 'https://www.lebikini.com/programmation/rss';

    /** @var array */
    private $cache;

    /** @var Crawler */
    private $parser;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer)
    {
        parent::__construct($logger, $eventProducer);
        $this->cache = [];
        $this->parser = new Crawler;
    }

    public function parse(bool $incremental): void
    {
        //Récupère les différents liens à parser depuis le flux RSS
        $links = $this->parseRSS();

        foreach ($links as $link) {
            try {
                $event = $this->getInfosEvent($link);
                $this->publish($event);
            } catch (Exception $e) {
                $this->logException($e);
            }
        }
    }

    private function getInfosEvent(string $url): array
    {
        //Positionne le parser sur l'url
        $this->parseContent($url);

        $tab_retour = [];

        $full_date = $this->parser->filter('#date')->text();
        $date_affichage = $this->parseDate($full_date);
        $tab_retour['reservation_internet'] = \implode(' ', $this->parser->filter('#reservation a.boutonReserverSpectacle')->each(function (Crawler $item) {
            return $item->attr('href');
        }));
        $tab_retour['date_debut'] = DateTime::createFromFormat('Y-n-d', $date_affichage);
        $tab_retour['horaires'] = \preg_replace('/^(.+)à (\d{2}):(\d{2})$/i', 'A $2h$3', $full_date);
        $tab_retour['nom'] = $this->parser->filter('#blocContenu h2')->text();
        $tab_retour['placeName'] = $this->parser->filter('#salle h3')->text();
        $adresse = $this->parser->filter('#salle #adresse')->html();
        $tab_retour['descriptif'] = $this->parser->filter('#texte')->html();
        $tab_retour['url'] = $this->parser->filter('#blocImage a[rel=shadowbox]')->attr('href');
        $tab_retour['external_id'] = 'BKN-' . str_replace('img', '', $this->parser->filter('#blocImage a[rel=shadowbox] img')->attr('id'));
        $tab_retour['source'] = $url;
        $tab_retour['type_manifestation'] = 'Concert, Musique';

        /*
         *  Rond point Madame de Mondonville Boulevard Netwiller
            TOULOUSE
         */
        $full_adresse = \preg_split('/<br\/?>/i', $adresse);
        $ville = $full_adresse[1];
        if (\preg_match('/\d/i', $ville)) {
            $tab_retour['placePostalCode'] = \preg_replace('/\D/i', '', $ville);
            $ville = \preg_replace('/\d/i', '', $ville);
        }

        $tab_retour['placeStreet'] = \preg_replace("#^(\d+), #", '$1 ', $full_adresse[0]);
        $tab_retour['placePostalCode'] = $this->cache[$url] ?? null;
        $tab_retour['placeCity'] = $ville;
        $tab_retour['placeCountryName'] = 'France';

        $this->parser->filter('#blocContenu')->children()->each(function (Crawler $sibling) use (&$tab_retour) {
            if ('prix' === $sibling->attr('id')) {
                $tab_retour['tarif'] = \trim($sibling->text());

                return $sibling;
            }

            return false;
        });

        $this->parser->filter('#blocContenu')->children()->each(function (Crawler $sibling) use (&$tab_retour) {
            if ('type' === $sibling->attr('id')) {
                $tab_retour['theme_manifestation'] = \preg_replace('/style\s?:\s?/i', '', \trim($sibling->text()));
                $tab_retour['theme_manifestation'] = \implode(',', \explode('/', $tab_retour['theme_manifestation']));

                return $sibling;
            }

            return false;
        });

        return $tab_retour;
    }

    private function parseDate($date)
    {
        $tabMois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return \preg_replace_callback("/(.+)(\d{2}) (" . \implode('|', $tabMois) . ") (\d{4})(.*)/iu",
            function ($items) use ($tabMois) {
                return $items[4] . '-' . (\array_search($items[3], $tabMois) + 1) . '-' . $items[2];
            }, $date);
    }

    private function parseRSS()
    {
        $this->parseContent(self::RSS_URL, 'XML');

        return \array_filter($this->parser->filter('item')->each(function (Crawler $item) {
            //Store postal code in cache as it's only displayed here
            if (\preg_match('/<link>(.+)<description>(.+)(\d{5}).*<\/description>/im', \preg_replace('/\n/', '', $item->html()), $matches)) {
                $this->cache[$matches[1]] = $matches[3];

                return \trim($matches[1]);
            }

            return false;
        }));
    }

    private function parseContent(string $url, string $type = 'HTML')
    {
        $this->parser->clear();

        try {
            $this->parser->addContent(\file_get_contents($url), $type);
        } catch (Exception $e) {
            $this->logException($e);
        }
    }

    public static function getParserName(): string
    {
        return 'Bikini';
    }
}
