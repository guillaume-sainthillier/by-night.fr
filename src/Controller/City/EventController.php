<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\Comment;
use App\Form\Type\CommentType;
use App\Picture\EventProfilePicture;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use SocialLinks\Page;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EventController extends BaseController
{
    protected function getCreateCommentForm(Comment $comment, Event $event)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
            'method' => 'POST',
        ])
            ->add('poster', SubmitType::class, [
                'label' => 'Poster',
                'attr' => [
                    'class' => 'btn btn-primary btn-submit btn-raised',
                    'data-loading-text' => 'En cours...',
                ],
            ]);
    }

    /**
     * @Route("/soiree/{slug}--{id}", name="app_event_details", requirements={"slug": "[^/]+", "id": "\d+"})
     * @Route("/soiree/{slug}", name="app_event_details_old", requirements={"slug": "[^/]+"})
     * @ReverseProxy(expires="+1 month")
     */
    public function detailsAction(Location $location, $slug, $id = null)
    {
        $result = $this->checkEventUrl($location->getSlug(), $slug, $id);
        if ($result instanceof Response) {
            return $result;
        }
        $event = $result;

        return $this->render('City/Event/get.html.twig', [
            'location' => $location,
            'event' => $event,
        ]);
    }

    /**
     * @Cache(expires="+12 hours", smaxage="43200")
     *
     * @param Event $event
     * @param EventProfilePicture $eventProfilePicture
     *
     * @return Response
     *
     * @throws Exception
     */
    public function shareAction(Event $event, EventProfilePicture $eventProfilePicture)
    {
        $link = $this->generateUrl('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $eventProfile = $eventProfilePicture->getOriginalPictureUrl($event);

        $page = new Page([
            'url' => $link,
            'title' => $event->getNom(),
            'text' => $event->getDescriptif(),
            'image' => $eventProfile,
        ]);

        $page->shareCount(['twitter', 'facebook']);

        return $this->render('City/Hinclude/shares.html.twig', [
            'shares' => [
                'facebook' => $page->facebook,
                'twitter' => $page->twitter
            ],
        ]);
    }
}
