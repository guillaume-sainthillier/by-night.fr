<?php

namespace App\News;

use Doctrine\Common\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use App\Entity\Agenda;
use App\Entity\News;
use App\Social\FacebookAdmin;
use App\Social\Twitter;
use Twig\Environment;

class NewsManager
{
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FacebookAdmin
     */
    private $facebook;

    /**
     * @var Twitter
     */
    private $twitter;

    public function __construct(ObjectManager $em, Environment $twig, FacebookAdmin $facebook, Twitter $twitter, LoggerInterface $logger)
    {
        $this->em       = $em;
        $this->twig     = $twig;
        $this->facebook = $facebook;
        $this->twitter  = $twitter;
        $this->logger   = $logger;
    }

    public function postNews(News $news, $wordpressPostId, $shortTitle, $longTitle, $url, $imageUrl)
    {
        $success = true;
        if (!$news->getFbPostId()) {
            try {
                $postId = $this->facebook->postNews($longTitle, $url, $imageUrl);
                $news->setTweetPostId($postId);
                $success = $success && true;
            } catch (\Exception $e) {
                $success = false;
                $this->logger->critical($e);
            }
        }

        if (!$news->getTweetPostId()) {
            try {
                $postId = $this->twitter->postNews($shortTitle, $url);
                $news->setTweetPostId($postId);
                $success = $success && true;
            } catch (\Exception $e) {
                $success = false;
                $this->logger->critical($e);
            }
        }
        $news->setWordpressPostId($wordpressPostId);
        $this->em->persist($news);
        $this->em->flush();

        return $success;
    }

    public function getNewsDatas(\DateTime $from, \DateTime $to)
    {
        $datas = $this->em->getRepository('App:Agenda')->findByInterval($from, $to);

        $participants = [];
        foreach ($datas as $site => $events) {
            $participants[$site] = 0;
            foreach ($events as $event) {
                /*
                 * @var Agenda $event
                 */
                $participants[$site] += $event->getFbInterets() + $event->getFbParticipations();
            }
        }

        \arsort($participants);
        $totalPartcipants = \array_sum($participants);

        $news = $this->em->getRepository('App:News')->findOneBy([
            'dateDebut' => $from,
            'dateFin'   => $to,
        ]);

        if (!$news) {
            $nextEdition = $this->em->getRepository('App:News')->findNextEdition();
            $news        = (new News())
                ->setDateDebut($from)
                ->setDateFin($to)
                ->setNumeroEdition($nextEdition);
        }

        $content = $this->twig->render('News/news.html.twig', [
            'datas'           => $datas,
            'topParticipants' => \array_slice($participants, 0, 5),
            'participants'    => $totalPartcipants,
        ]);

        return [
            'content' => $content,
            'events'  => $datas,
            'news'    => $news,
        ];
    }
}
