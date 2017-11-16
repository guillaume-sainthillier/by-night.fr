<?php

namespace TBN\SocialBundle\Social;

use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook as Client;
use Facebook\FacebookResponse;
use Facebook\GraphNodes\GraphEdge;
use Facebook\GraphNodes\GraphNode;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Utils\Monitor;
use TBN\SocialBundle\Exception\SocialException;
use TBN\UserBundle\Entity\User;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
class Facebook extends Social
{
    /**
     * @var Client
     */
    protected $client;

    const FIELDS            = 'id,name,updated_time,place,start_time,end_time,owner{category,website,phone,picture.type(large).redirect(false)},cover,ticket_uri,description,picture.type(large).redirect(false),attending_count,maybe_count';
    const USERS_FIELDS      = 'id,picture.type(large).redirect(false),cover';
    const STATS_FIELDS      = 'id,picture.type(large).redirect(false),cover,attending_count,maybe_count';
    const FULL_STATS_FIELDS = 'id,picture.type(large).redirect(false),cover,attending_count,maybe_count,attending.limit(500){name,picture.type(square).redirect(false)},maybe.limit(500){name,picture.type(square).redirect(false)}';
    const MEMBERS_FIELDS    = 'id,attending.offset(%offset%).limit(%limit%){name,picture.type(square).redirect(false)},maybe.offset(%offset%).limit(%limit%){name,picture.type(square).redirect(false)}';
    const ATTENDING_FIELDS  = 'id,name,picture.type(square).redirect(false)';
    const MIN_EVENT_FIELDS  = 'id,updated_time,owner{id}';

    protected function constructClient()
    {
        $this->client = new Client([
            'app_id'     => $this->id,
            'app_secret' => $this->secret,
        ]);
    }

    protected function findPaginated(GraphEdge $graph = null, $maxItems = 5000)
    {
        $datas = [];

        while (null !== $graph && $graph->count() > 0 && count($datas) < $maxItems) {
            try {
                if ($graph->getField('error_code')) {
                    Monitor::writeln(sprintf('<error>Erreur #%d : %s</error>', $graph->getField('error_code'), $graph->getField('error_msg')));
                    $graph = null;
                } else {
                    $currentData = $graph->all();
                    $datas       = array_merge($datas, $currentData);
                    $graph       = $this->client->next($graph);
                }
            } catch (FacebookSDKException $ex) {
                $graph = null;
                Monitor::writeln(sprintf('<error>Erreur dans findPaginated : %s</error>', $ex->getMessage()));
            }
        }

        return $datas;
    }

    protected function findAssociativeEvents(FacebookResponse $response)
    {
        $graph   = $response->getGraphNode();
        $indexes = $graph->getFieldNames();

        return array_map(function ($index) use ($graph) {
            return $graph->getField($index);
        }, $indexes);
    }

    protected function findPaginatedNodes(FacebookResponse $response)
    {
        $datas   = [];
        $graph   = $response->getGraphNode();
        $indexes = $graph->getFieldNames();
        foreach ($indexes as $index) {
            $subGraph = $graph->getField($index);
            $datas    = array_merge($datas, $this->next($subGraph));
        }

        return $datas;
    }

    protected function next(GraphEdge $graph = null)
    {
        if (!$graph) {
            return [];
        }

        $datas       = $graph->all();
        $nextRequest = $graph->getNextPageRequest();

        if (!$nextRequest) {
            return $datas;
        }

        try {
            $response = $this->client->getClient()->sendRequest($nextRequest);
            $nodes    = $response->getGraphNode();
            foreach ($nodes as $node) {
                $datas = array_merge($datas, $this->next($node));
            }
        } catch (FacebookSDKException $ex) {
            Monitor::writeln(sprintf('<error>Erreur dans next : %s</error>', $ex->getMessage()));
        }

        return $datas;
    }

    protected function findAssociativePaginated(FacebookResponse $response)
    {
        $datas   = [];
        $graph   = $response->getGraphNode();
        $indexes = $graph->getFieldNames();
        foreach ($indexes as $index) {
            $subGraph = $graph->getField($index);
            $datas    = array_merge($datas, $this->findPaginated($subGraph));
        }

        return $datas;
    }

    public function ensureGoodValue($value)
    {
        return '<<not-applicable>>' !== $value ? $value : null;
    }

    public function getPagePictureURL(GraphNode $object, $testCover = true, $testPicture = true)
    {
        $cover = $object->getField('cover');
        if ($testCover && $cover && $cover->getField('source')) {
            return $this->ensureGoodValue($cover->getField('source'));
        }

        $picture = $object->getField('picture');
        if ($testPicture && $picture && $picture->getField('url') && false === $picture->getField('is_silhouette')) {
            return $this->ensureGoodValue($picture->getField('url'));
        }
    }

    public function getNumberOfCount()
    {
        throw new SocialException("Les droits de l'utilisateur sont insufisants pour récupérer des infos sur une page Facebook");
    }

    protected function post(User $user, Agenda $agenda)
    {
        throw new SocialException("Les droits de l'utilisateur sont insufisants pour poster sur Facebook");
    }

    protected function afterPost(User $user, Agenda $agenda)
    {
        throw new SocialException('Les droits du système sont insufisants pour poster sur une page Facebook');
    }

    protected function getDuree($dateDebut, $dateFin)
    {
        return $dateDebut === $dateFin ? 'Le '.$dateDebut : 'Du '.$dateDebut.' au '.$dateFin;
    }

    protected function getReadableDate(\DateTime $date = null, $dateFormat = \IntlDateFormatter::FULL, $timeFormat = \IntlDateFormatter::NONE)
    {
        if (!$date) {
            return;
        }

        $intl = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat);

        return $intl->format($date);
    }

    public function getName()
    {
        return 'Facebook';
    }
}
