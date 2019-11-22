<?php

namespace App\Social;

use App\App\SocialManager;
use App\Entity\SiteInfo;
use App\Picture\EventProfilePicture;
use App\Utils\Monitor;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\FacebookClient;
use Facebook\FacebookResponse;
use Facebook\GraphNodes\GraphNode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
class FacebookAdmin extends Facebook
{
    /**
     * @var SiteInfo
     */
    protected $siteInfo;
    /**
     * @var bool
     */
    protected $_isInitialized;

    protected function init()
    {
        parent::init();

        if (!$this->_isInitialized) {
            $this->_isInitialized = true;
            $this->siteInfo = $this->socialManager->getSiteInfo();

            if ($this->siteInfo && $this->siteInfo->getFacebookAccessToken()) {
                $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
            }
        }
    }

    public function getNumberOfCount()
    {
        $this->init();

        try {
            $page = $this->getPageFromId($this->socialManager->getFacebookIdPage(), ['fields' => 'fan_count']);

            return $page->getField('fan_count');
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return 0;
    }

    public function getPageFromId($id_page, $params = [])
    {
        $this->init();
        $accessToken = $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null;
        $request = $this->client->sendRequest('GET',
            '/' . $id_page,
            $params,
            $accessToken
        );

        return $request->getGraphPage();
    }

    public function getUserImagesFromIds(array $ids_users)
    {
        $urls = [];
        foreach ($ids_users as $id_user) {
            $urls[$id_user] = sprintf(
                '%s/%s/picture?width=1500&height=1500',
                FacebookClient::BASE_GRAPH_URL,
                $id_user
            );
        }

        return $urls;
    }
}
