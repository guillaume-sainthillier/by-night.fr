<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/05/2017
 * Time: 17:16
 */

namespace AppBundle\Geocoder;

use AppBundle\Entity\Place;
use AppBundle\Geolocalize\Boundary;
use AppBundle\Geolocalize\Coordinate;
use AppBundle\Reject\Reject;
use AppBundle\Utils\Firewall;
use Doctrine\Common\Cache\CacheProvider;
use Ivory\GoogleMap\Service\Geocoder\Response\GeocoderStatus;
use Ivory\GoogleMap\Service\Place\Detail\PlaceDetailService;
use Ivory\GoogleMap\Service\Place\Detail\Request\PlaceDetailRequest;
use Ivory\GoogleMap\Service\Place\Search\PlaceSearchService;
use Ivory\GoogleMap\Service\Place\Search\Request\TextPlaceSearchRequest;
use Ivory\Serializer\SerializerInterface;

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
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Firewall
     */
    private $firewall;

    public function __construct(CacheProvider $cache, PlaceSearchService $geocoder, PlaceDetailService $placeGeocoder, SerializerInterface $serializer, Firewall $firewall)
    {
        $this->cache = $cache;
        $this->geocoder = $geocoder;
        $this->placeGeocoder = $placeGeocoder;
        $this->serializer = $serializer;
        $this->firewall = $firewall;
    }

    public function geocode(Place $place) {
        $nom = $place->getNom();
        if(! $nom) {
            return;
        }

        $data = $this->cache->fetch($nom);
        if($data === false) {
            $request = new TextPlaceSearchRequest($place->getNom());
            $response = $this->geocoder->process($request);
            $data = [];
            foreach($response as $results) {
                $data['status'] = $results->getStatus();
                $data['results'] = [];
                foreach($results->getResults() as $place) {
                    $result = [
                        'id' => $place->getId(),
                        'placeId' => $place->getPlaceId(),
                        'name' => $place->getName(),
                        'formattedAddress' => $place->getFormattedAddress(),
                    ];

                    if($place->getGeometry() && $place->getGeometry()->getLocation()) {
                        $geometry = $place->getGeometry();
                        $result['geometry'] = [
                            "lat" => $geometry->getLocation()->getLatitude(),
                            "lng" => $geometry->getLocation()->getLongitude(),
                        ];
                    }
                    $data['results'][] = $result;
                }
                $this->cache->save($nom, $data);
                break;
            }
        }

        switch($data['status']) {
            case GeocoderStatus::ZERO_RESULTS:
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


        $candidatePlace = null;
        foreach($data['results'] as $result) {
            if(isset($result['geometry'])) {
                $candidateCoordinate = new Coordinate($result['geometry']['lat'], $result['geometry']['lng']);
                $placeCoordinate = new Boundary($place->getLatitude(), $place->getLongitude());
                if($this->firewall->isLocationBounded($candidateCoordinate, $placeCoordinate)) {
                    $candidatePlace = $result;
                    break;
                }
            }
        }

        if(! $candidatePlace) {
            $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
            return;
        }

        $gmapPlace = $this->cache->fetch($candidatePlace['placeId']);
        if($gmapPlace === false) {
            $request = new PlaceDetailRequest($candidatePlace['placeId']);
            $response = $this->placeGeocoder->process($request);
            switch($response->getStatus()) {
                case GeocoderStatus::ZERO_RESULTS:
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
            $datas = [];
            if($gmapPlace) {
                foreach($gmapPlace->getAddressComponents() as $addressComponent) {
                    if(in_array("country", $addressComponent->getTypes())) {
                        $datas['country'] = $addressComponent->getShortName();
                    }elseif(in_array("administrative_area_level_1", $addressComponent->getTypes())) {
                        $datas['admin_zone_1'] = $addressComponent->getLongName();
                    }elseif(in_array("administrative_area_level_2", $addressComponent->getTypes())) {
                        $datas['admin_zone_2'] = $addressComponent->getLongName();
                    }elseif(in_array("postal_code", $addressComponent->getTypes())) {
                        $datas['postal_code'] = $addressComponent->getLongName();
                    }elseif(in_array("locality", $addressComponent->getTypes())) {
                        $datas['city'] = $addressComponent->getLongName();
                    }
                }
            }

            $this->cache->save($candidatePlace['placeId'], $datas);
        }

        dump($datas);
        die;

        if(empty($this->i)) {
            $this->i = 0;
        }
        $this->i++;

        if($this->i === 3)
        die;
    }
}
