<?php


namespace App\Geocoder;

use App\Entity\Place;
use App\Geolocalize\Boundary;
use App\Geolocalize\Coordinate;
use App\Reject\Reject;
use App\Utils\Firewall;
use Doctrine\Common\Cache\CacheProvider;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\QuotaExceeded;
use Geocoder\Model\AdminLevel;
use Geocoder\Provider\GoogleMaps\Model\GoogleAddress;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

class PlaceGeocoder
{
    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * @var Provider
     */
    private $geocoder;

    /**
     * @var Firewall
     */
    private $firewall;

    public function __construct(CacheProvider $cache, Provider $geocoder, Firewall $firewall)
    {
        $this->cache = $cache;
        $this->geocoder = $geocoder;
        $this->firewall = $firewall;
    }

    public function geocodeCoordinates(Place $place)
    {
        $key = \sprintf('%f;%f', \round($place->getLatitude(), 6), \round($place->getLongitude(), 6));
        $data = $this->cache->fetch($key);
        if (false === $data) {
            try {
                $responses = $this->geocoder->reverseQuery(ReverseQuery::fromCoordinates($place->getLatitude(), $place->getLongitude()));
            } catch (QuotaExceeded $e) {
                $place->getReject()->addReason(Reject::GEOCODE_LIMIT);

                return;
            } catch (InvalidServerResponse | InvalidCredentials $e) {
                $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

                return;
            }

            $data = [];
            foreach ($responses as $result) {
                $data = $this->getPlaceInfos($result);

                break;
            }
            $this->cache->save($key, $data);
        }

        if (!\count($data)) {
            $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

            return;
        }

        $this->setPlaceInfos($place, $data);
    }

    public function geocodePlace(Place $place)
    {
        if (null === $place->getLongitude() || null === $place->getLatitude()) {
            return;
        }

        $nom = $place->getNom();
        if (!$nom) {
            return;
        }

        $data = $this->cache->fetch($nom);
        if (false === $data) {
            try {
                $query = GeocodeQuery::create($nom);
                $responses = $this->geocoder->geocodeQuery($query);
            } catch (QuotaExceeded $e) {
                $place->getReject()->addReason(Reject::GEOCODE_LIMIT);

                return;
            } catch (InvalidServerResponse | InvalidCredentials $e) {
                $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

                return;
            }

            $data = [];
            foreach ($responses as $response) {
                /** @var GoogleAddress $response */
                $result = \array_merge([
                    'placeId' => $response->getId(),
                ], $response->toArray());

                if ($response->getCoordinates()) {
                    $geometry = $response->getCoordinates();
                    $result['geometry'] = [
                        'lat' => $geometry->getLatitude(),
                        'lng' => $geometry->getLongitude(),
                    ];
                }
                $data[] = $result;
            }

            $this->cache->save($nom, $data);
        }

        if (!\count($data)) {
            $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

            return;
        }

        $candidatePlace = null;
        foreach ($data as $result) {
            $address = GoogleAddress::createFromArray($result);
            if ($address->getCoordinates()) {
                $candidateCoordinate = new Coordinate($address->getCoordinates()->getLatitude(), $address->getCoordinates()->getLongitude());
                $placeCoordinate = new Boundary($place->getLatitude(), $place->getLongitude());
                if ($this->firewall->isLocationBounded($candidateCoordinate, $placeCoordinate)) {
                    $candidatePlace = $address;

                    break;
                }
            }
        }

        if (!$candidatePlace) {
            $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);

            return;
        }

        $gmapPlace = $this->getPlaceInfos($candidatePlace);
        $this->setPlaceInfos($place, $gmapPlace);
    }

    private function setPlaceInfos(Place $place, array $gmapPlace)
    {
        if (isset($gmapPlace['country'])) {
            $place->setCountryName($gmapPlace['country']);
        }

        if (isset($gmapPlace['city'])) {
            $place->setVille($gmapPlace['city']);
        }

        if (isset($gmapPlace['postal_code'])) {
            $place->setCodePostal($gmapPlace['postal_code']);
        }
    }

    private function getPlaceInfos(GoogleAddress $address)
    {
        $datas = [];
        if ($address->getPostalCode()) {
            $datas['postal_code'] = $address->getPostalCode();
        }

        if ($address->getLocality()) {
            $datas['city'] = $address->getLocality();
        }

        if ($address->getStreetName()) {
            $datas['rue'] = \trim(\sprintf('%s %s', $address->getStreetNumber(), $address->getStreetName()));
        }

        if ($address->getCountry()) {
            $datas['country'] = $address->getCountry()->getCode();
        }

        foreach ($address->getAdminLevels() as $adminLevel) {
            /** @var AdminLevel $adminLevel */
            if (1 === $adminLevel->getLevel()) {
                $datas['admin_zone_1'] = $adminLevel->getName();
            } elseif (2 === $adminLevel->getLevel()) {
                $datas['admin_zone_2'] = $adminLevel->getName();
            }
        }

        return $datas;
    }
}
