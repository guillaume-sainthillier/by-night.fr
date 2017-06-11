<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/05/2017
 * Time: 17:16.
 */

namespace AppBundle\Geocoder;

use AppBundle\Entity\Place;
use AppBundle\Geolocalize\Boundary;
use AppBundle\Geolocalize\Coordinate;
use AppBundle\Reject\Reject;
use AppBundle\Utils\Firewall;
use Doctrine\Common\Cache\CacheProvider;
use Ivory\GoogleMap\Service\Base\AddressComponent;
use Ivory\GoogleMap\Service\Geocoder\GeocoderService;
use Ivory\GoogleMap\Service\Geocoder\Request\GeocoderCoordinateRequest;
use Ivory\GoogleMap\Service\Geocoder\Response\GeocoderStatus;
use Ivory\GoogleMap\Service\Place\Detail\PlaceDetailService;
use Ivory\GoogleMap\Service\Place\Detail\Request\PlaceDetailRequest;
use Ivory\GoogleMap\Service\Place\Search\PlaceSearchService;
use Ivory\GoogleMap\Service\Place\Search\Request\TextPlaceSearchRequest;

class PlaceGeocoder
{
    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * @var PlaceSearchService
     */
    private $geocoder;

    /**
     * @var PlaceDetailService
     */
    private $placeGeocoder;

    /**
     * @var GeocoderService
     */
    private $reverseGeocoder;

    /**
     * @var Firewall
     */
    private $firewall;

    public function __construct(CacheProvider $cache, PlaceSearchService $geocoder, PlaceDetailService $placeGeocoder, GeocoderService $reverseGeocoder, Firewall $firewall)
    {
        $this->cache           = $cache;
        $this->geocoder        = $geocoder;
        $this->placeGeocoder   = $placeGeocoder;
        $this->reverseGeocoder = $reverseGeocoder;
        $this->firewall        = $firewall;
    }

    public function geocodeCoordinates(Place $place)
    {
        $key  = sprintf('%f.%f', round($place->getLatitude(), 6), round($place->getLongitude(), 6));
        $data = $this->cache->fetch($key);
        if ($data === false) {
            $request  = new GeocoderCoordinateRequest(new Coordinate($place->getLatitude(), $place->getLongitude()));
            $response = $this->reverseGeocoder->geocode($request);
            $data     = [];

            switch ($response->getStatus()) {
                case GeocoderStatus::ERROR:
                case GeocoderStatus::UNKNOWN_ERROR:
                case GeocoderStatus::INVALID_REQUEST:
                case GeocoderStatus::REQUEST_DENIED:
                    $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

                    return;
                case GeocoderStatus::OVER_QUERY_LIMIT:
                    $place->getReject()->addReason(Reject::GEOCODE_LIMIT);

                    return;
            }

            foreach ($response->getResults() as $result) {
                $data = $this->getPlaceInfos($result->getAddressComponents());
                break;
            }
        }

        if (!count($data)) {
            $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

            return;
        }

        $this->setPlaceInfos($place, $data);
    }

    public function geocodePlace(Place $place)
    {
        $nom = $place->getNom();
        if (!$nom) {
            return;
        }

        $data = $this->cache->fetch($nom);
        if ($data === false) {
            $request  = new TextPlaceSearchRequest($nom);
            $response = $this->geocoder->process($request);
            $data     = [];
            foreach ($response as $results) {
                $data['status']  = $results->getStatus();
                $data['results'] = [];

                switch ($data['status']) {
                    case GeocoderStatus::ERROR:
                    case GeocoderStatus::UNKNOWN_ERROR:
                    case GeocoderStatus::INVALID_REQUEST:
                    case GeocoderStatus::REQUEST_DENIED:
                        $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

                        return;
                    case GeocoderStatus::OVER_QUERY_LIMIT:
                        $place->getReject()->addReason(Reject::GEOCODE_LIMIT);

                        return;
                }

                foreach ($results->getResults() as $candidatePlace) {
                    $result = [
                        'id'               => $candidatePlace->getId(),
                        'placeId'          => $candidatePlace->getPlaceId(),
                        'name'             => $candidatePlace->getName(),
                        'formattedAddress' => $candidatePlace->getFormattedAddress(),
                    ];

                    if ($candidatePlace->getGeometry() && $candidatePlace->getGeometry()->getLocation()) {
                        $geometry           = $candidatePlace->getGeometry();
                        $result['geometry'] = [
                            'lat' => $geometry->getLocation()->getLatitude(),
                            'lng' => $geometry->getLocation()->getLongitude(),
                        ];
                    }
                    $data['results'][] = $result;
                }
                $this->cache->save($nom, $data);
                break;
            }
        }

        switch ($data['status']) {
            case GeocoderStatus::ZERO_RESULTS:
                $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

                return;
        }

        $candidatePlace = null;
        foreach ($data['results'] as $result) {
            if (isset($result['geometry'])) {
                $candidateCoordinate = new Coordinate($result['geometry']['lat'], $result['geometry']['lng']);
                $placeCoordinate     = new Boundary($place->getLatitude(), $place->getLongitude());
                if ($this->firewall->isLocationBounded($candidateCoordinate, $placeCoordinate)) {
                    $candidatePlace = $result;
                    break;
                }
            }
        }

        if (!$candidatePlace) {
            $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);

            return;
        }

        $gmapPlace = $this->cache->fetch($candidatePlace['placeId']);
        if ($gmapPlace === false) {
            $request  = new PlaceDetailRequest($candidatePlace['placeId']);
            $response = $this->placeGeocoder->process($request);
            switch ($response->getStatus()) {
                case GeocoderStatus::ERROR:
                case GeocoderStatus::UNKNOWN_ERROR:
                case GeocoderStatus::INVALID_REQUEST:
                case GeocoderStatus::REQUEST_DENIED:
                    $place->getReject()->addReason(Reject::BAD_PLACE_NAME);

                    return;
                case GeocoderStatus::OVER_QUERY_LIMIT:
                    $place->getReject()->addReason(Reject::GEOCODE_LIMIT);

                    return;
            }

            $gmapPlace = $response->getResult();
            $datas     = [];
            if ($gmapPlace) {
                $datas = $this->getPlaceInfos($gmapPlace->getAddressComponents());
            }

            $this->cache->save($candidatePlace['placeId'], $datas);
            $gmapPlace = $datas;
        }

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

        if (isset($gmapPlace['rue']) && !$place->getRue()) {
            $place->setRue($gmapPlace['rue']);
        }
    }

    /**
     * @param AddressComponent[] $addresseComponents
     *
     * @return array
     */
    private function getPlaceInfos(array $addresseComponents)
    {
        $datas = [];
        foreach ($addresseComponents as $addressComponent) {
            if (in_array('country', $addressComponent->getTypes())) {
                $datas['country'] = $addressComponent->getLongName();
            } elseif (in_array('administrative_area_level_1', $addressComponent->getTypes())) {
                $datas['admin_zone_1'] = $addressComponent->getLongName();
            } elseif (in_array('administrative_area_level_2', $addressComponent->getTypes())) {
                $datas['admin_zone_2'] = $addressComponent->getLongName();
            } elseif (in_array('postal_code', $addressComponent->getTypes())) {
                $datas['postal_code'] = $addressComponent->getLongName();
            } elseif (in_array('locality', $addressComponent->getTypes())) {
                $datas['city'] = $addressComponent->getLongName();
            } elseif (in_array('route', $addressComponent->getTypes())) {
                $datas['rue'] = $addressComponent->getLongName();
            }
        }

        return $datas;
    }
}
