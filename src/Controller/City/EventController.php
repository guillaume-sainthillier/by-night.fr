<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
use App\Entity\Calendrier;
use App\Entity\Comment;
use App\Entity\User;
use App\Form\Type\CommentType;
use App\Picture\EventProfilePicture;
use App\Social\FacebookAdmin;
use Doctrine\Common\Cache\Cache as DoctrineCache;
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

        $comment = new Comment();
        $form = $this->getCreateCommentForm($comment, $agenda);
        $nbComments = $agenda->getCommentaires()->count();

        return $this->render('City/Event/get.html.twig', [
            'location' => $location,
            'soiree' => $agenda,
            'form' => $form->createView(),
            'nb_comments' => $nbComments,
        ]);
    }

    /**
     * @ReverseProxy(expires="1 year")
     */
    public function tendances(Agenda $agenda, DoctrineCache $memoryCache, FacebookAdmin $facebookAdmin)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        $participer = false;
        $interet = false;

        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $repoCalendrier = $em->getRepository(Calendrier::class);
            $calendrier = $repoCalendrier->findOneBy(['user' => $user, 'agenda' => $agenda]);
            if (null !== $calendrier) {
                $participer = $calendrier->getParticipe();
                $interet = $calendrier->getInteret();
            }

            if ($agenda->getFacebookEventId() && $user->getInfo() && $user->getInfo()->getFacebookId()) {
                $key = 'users.' . $user->getId() . '.stats.' . $agenda->getId();
                if (!$memoryCache->contains($key)) {
                    $stats = $facebookAdmin->getUserEventStats($agenda->getFacebookEventId(), $user->getInfo()->getFacebookId(), $user->getInfo()->getFacebookAccessToken());
                    $memoryCache->save($key, $stats);
                }
                $stats = $memoryCache->fetch($key);

                if ($stats['participer'] || $stats['interet']) {
                    if (null === $calendrier) {
                        $calendrier = new Calendrier();
                        $calendrier->setUser($user)->setAgenda($agenda);
                    }

                    $participer = $calendrier->getParticipe() || $stats['participer'];
                    $interet = $calendrier->getInteret() || $stats['interet'];

                    $calendrier
                        ->setParticipe($participer)
                        ->setInteret($interet);

                    $em->persist($calendrier);
                    $em->flush();
                }
            }
        }

        return $this->render('City/Hinclude/tendances.html.twig', [
            'soiree' => $agenda,
            'tendances' => $repo->findAllTendances($agenda),
            'count_participer' => $agenda->getParticipations() + $agenda->getFbParticipations(),
            'count_interets' => $agenda->getInterets() + $agenda->getFbInterets(),
            'participer' => $participer,
            'interet' => $interet,
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
