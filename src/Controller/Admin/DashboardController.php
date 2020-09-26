<?php

namespace App\Controller\Admin;

use App\Entity\AppOAuth;
use App\Entity\Comment;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\ParserData;
use App\Entity\ParserHistory;
use App\Entity\Place;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Entity\UserOAuth;
use App\Picture\UserProfilePicture;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\CrudUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardController extends AbstractDashboardController
{
    private UserProfilePicture $userProfilePicture;

    public function __construct(UserProfilePicture $userProfilePicture)
    {
        $this->userProfilePicture = $userProfilePicture;
    }

    /**
     * @Route("/", name="admin")
     */
    public function index(): Response
    {
        // redirect to some CRUD controller
        $routeBuilder = $this->get(CrudUrlGenerator::class)->build();

        return $this->redirect($routeBuilder->setController(EventCrudController::class)->generateUrl());
    }


    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('By Night Administration')
            ->setTranslationDomain(false);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->add(Crud::PAGE_EDIT, Action::DELETE)
            ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToUrl('Back to Home', 'fas fa-home', '../')->setLinkTarget('_blank')->setLinkRel('noreferrer');

        yield MenuItem::section('Commnunity', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('Events', 'fas fa-calendar-alt', Event::class);
        yield MenuItem::linkToCrud('Places', 'fas fa-map-marker-alt', Place::class);
        yield MenuItem::linkToCrud('Comments', 'fas fa-comment', Comment::class);
        yield MenuItem::linkToCrud('User Event', 'fas fa-calendar-alt', UserEvent::class);

        yield MenuItem::section('Users', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('Users', 'fas fa-users', User::class);
        yield MenuItem::linkToCrud('User socials', 'fas fa-bullhorn', UserOAuth::class);

        yield MenuItem::section('Administration', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('Countries', 'fas fa-globe-europe', Country::class);
        yield MenuItem::linkToCrud('Site socials', 'fas fa-bullhorn', AppOAuth::class);
        yield MenuItem::linkToCrud('Explorations', 'far fa-eye', ParserData::class);
        yield MenuItem::linkToCrud('Historique Maj', 'fas fa-history', ParserHistory::class);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        return parent::configureUserMenu($user)
            ->setAvatarUrl($this->userProfilePicture->getProfilePicture($user, [
                'w' => 21,
                'h' => 21,
                'fit' => 'crop',
                'dpr' => 2
            ]));
    }
}
