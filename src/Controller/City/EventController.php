<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
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
    protected function getCreateCommentForm(Comment $comment, Agenda $soiree)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_comment_new', ['id' => $soiree->getId()]),
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
     * @Route("/soiree/{slug}--{id}", name="app_agenda_details", requirements={"slug": "[^/]+", "id": "\d+"})
     * @Route("/soiree/{slug}", name="app_agenda_details_old", requirements={"slug": "[^/]+"})
     * @ReverseProxy(expires="+1 month")
     */
    public function detailsAction(Location $location, $slug, $id = null)
    {
        $result = $this->checkEventUrl($location->getSlug(), $slug, $id);
        if ($result instanceof Response) {
            return $result;
        }
        $agenda = $result;

        return $this->render('City/Event/get.html.twig', [
            'location' => $location,
            'soiree' => $agenda,
        ]);
    }

    /**
     * @Cache(expires="+12 hours", smaxage="43200")
     *
     * @param Agenda $agenda
     * @param EventProfilePicture $eventProfilePicture
     *
     * @return Response
     *
     * @throws Exception
     */
    public function shareAction(Agenda $agenda, EventProfilePicture $eventProfilePicture)
    {
        $link = $this->generateUrl('app_agenda_details', [
            'slug' => $agenda->getSlug(),
            'id' => $agenda->getId(),
            'location' => $agenda->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $eventProfile = $eventProfilePicture->getOriginalPictureUrl($agenda);

        $page = new Page([
            'url' => $link,
            'title' => $agenda->getNom(),
            'text' => $agenda->getDescriptif(),
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
