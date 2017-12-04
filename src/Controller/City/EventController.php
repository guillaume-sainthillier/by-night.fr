<?php

namespace App\Controller\City;

use App\Entity\City;
use App\Picture\EventProfilePicture;
use App\Social\FacebookAdmin;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\Comment;
use App\Form\Type\CommentType;
use App\Controller\TBNController as Controller;
use SocialLinks\Page;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use FOS\HttpCacheBundle\Configuration\Tag;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\BrowserCache;
use App\Entity\Agenda;
use App\Entity\Calendrier;
use App\Invalidator\EventInvalidator;
use App\Entity\User;

class EventController extends Controller
{
    protected function getCreateCommentForm(Comment $comment, Agenda $soiree)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('tbn_comment_new', ['id' => $soiree->getId()]),
            'method' => 'POST',
        ])
            ->add('poster', SubmitType::class, [
                'label' => 'Poster',
                'attr'  => [
                    'class'             => 'btn btn-primary btn-submit btn-raised',
                    'data-loading-text' => 'En cours...',
                ],
            ]);
    }

    /**
     * @Tag("detail-event")
     * @Route("/soiree/{slug}--{id}", name="tbn_agenda_details", requirements={"slug": "[^/]+", "id": "\d+"})
     * @Route("/soiree/{slug}", name="tbn_agenda_details_old", requirements={"slug": "[^/]+"})
     * @BrowserCache(false)
     */
    public function detailsAction(City $city, $slug, $id = null, SymfonyResponseTagger $responseTagger)
    {
        $result = $this->checkEventUrl($city, $slug, $id);
        if ($result instanceof Response) {
            return $result;
        }
        $agenda = $result;

        $comment    = new Comment();
        $form       = $this->getCreateCommentForm($comment, $agenda);
        $nbComments = $agenda->getCommentaires()->count();

        $response = $this->render('City/Agenda/details.html.twig', [
            'city'        => $city,
            'soiree'      => $agenda,
            'form'        => $form->createView(),
            'nb_comments' => $nbComments,
            'stats'       => $this->getAgendaStats($agenda),
        ]);

        $now = new \DateTime();
        if ($agenda->getDateFin() < $now) {
            $expires = $now;
            $expires->modify('+1 year');
            $ttl = 31536000;
        } else {
            list($expires, $ttl) = $this->getSecondsUntil(168);
        }

        $response
            ->setSharedMaxAge($ttl)
            ->setExpires($expires);

        $responseTagger->addTags([
            EventInvalidator::getEventDetailTag($agenda),
        ]);

        return $response;
    }

    /**
     * @Cache(expires="+12 hours", smaxage="43200")
     *
     * @param Agenda $agenda
     *
     * @return Response
     */
    public function shareAction(Agenda $agenda, EventProfilePicture $eventProfilePicture)
    {
        $link = $this->generateUrl('tbn_agenda_details', [
            'slug' => $agenda->getSlug(),
            'id'   => $agenda->getId(),
            'city' => $agenda->getPlace()->getCity()->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $eventProfile = $eventProfilePicture->getOriginalPictureUrl($agenda);

        $page = new Page([
            'url'   => $link,
            'title' => $agenda->getNom(),
            'text'  => $agenda->getDescriptif(),
            'image' => $eventProfile,
        ]);

        $page->shareCount(['twitter', 'facebook', 'plus']);

        return $this->render('City/Hinclude/shares.html.twig', [
            'shares' => [
                'facebook'    => $page->facebook,
                'twitter'     => $page->twitter,
                'google-plus' => $page->plus,
            ],
        ]);
    }

    protected function getAgendaStats(Agenda $agenda, FacebookAdmin $facebookAdmin)
    {
        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        $participer = false;
        $interet    = false;

        $user = $this->getUser();
        if ($user) {
            /**
             * @var User
             */
            $repoCalendrier = $em->getRepository(Calendrier::class);
            $calendrier     = $repoCalendrier->findOneBy(['user' => $user, 'agenda' => $agenda]);
            if (null !== $calendrier) {
                $participer = $calendrier->getParticipe();
                $interet    = $calendrier->getInteret();
            }

            if ($agenda->getFacebookEventId() && $user->getInfo() && $user->getInfo()->getFacebookId()) {
                $cache = $this->get('memory_cache');
                $key   = 'users.' . $user->getId() . '.stats.' . $agenda->getId();
                if (!$cache->contains($key)) {
                    $stats = $facebookAdmin->getUserEventStats($agenda->getFacebookEventId(), $user->getInfo()->getFacebookId(), $user->getInfo()->getFacebookAccessToken());
                    $cache->save($key, $stats);
                }
                $stats = $cache->fetch($key);

                if ($stats['participer'] || $stats['interet']) {
                    if (null === $calendrier) {
                        $calendrier = new Calendrier();
                        $calendrier->setUser($user)->setAgenda($agenda);
                    }

                    $participer = $calendrier->getParticipe() || $stats['participer'];
                    $interet    = $calendrier->getInteret() || $stats['interet'];

                    $calendrier
                        ->setParticipe($participer)
                        ->setInteret($interet);

                    $em->persist($calendrier);
                    $em->flush();
                }
            }
        }

        return [
            'tendancesParticipations' => $repo->findAllTendancesParticipations($agenda),
            'tendancesInterets'       => $repo->findAllTendancesInterets($agenda),
            'count_participer'        => $agenda->getParticipations() + $agenda->getFbParticipations(),
            'count_interets'          => $agenda->getInterets() + $agenda->getFbInterets(),
            'participer'              => $participer,
            'interet'                 => $interet,
        ];
    }
}
