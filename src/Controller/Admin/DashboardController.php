<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\User;
use App\Picture\UserProfilePicture;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly UserProfilePicture $userProfilePicture, private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    #[Override]
    public function index(): Response
    {
        // redirect to some CRUD controller
        $url = $this
            ->adminUrlGenerator
            ->setController(EventCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    #[Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('By Night Administration');
    }

    #[Override]
    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }

    #[Override]
    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->add(Crud::PAGE_EDIT, Action::DELETE)
        ;
    }

    #[Override]
    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            ->useCustomIconSet()
        ;
    }

    #[Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToUrl('Back to Home', 'lucide:home', '../')->setLinkTarget('_blank')->setLinkRel('noreferrer');

        yield MenuItem::section('Commnunity', 'lucide:folder-open');
        yield MenuItem::linkTo(EventCrudController::class, 'Events', 'lucide:calendar-days');
        yield MenuItem::linkTo(PlaceCrudController::class, 'Places', 'lucide:map-pin');
        yield MenuItem::linkTo(CommentCrudController::class, 'Comments', 'lucide:message-square');
        yield MenuItem::linkTo(UserEventCrudController::class, 'User Event', 'lucide:heart');
        yield MenuItem::linkTo(TagCrudController::class, 'Tags', 'lucide:tags');

        yield MenuItem::section('Users', 'lucide:folder-open');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'lucide:users');
        yield MenuItem::linkTo(UserOAuthCrudController::class, 'User socials', 'lucide:megaphone');

        yield MenuItem::section('Administration', 'lucide:folder-open');
        yield MenuItem::linkTo(ContentRemovalRequestCrudController::class, 'Demandes suppression', 'lucide:trash-2');
        yield MenuItem::linkTo(CountryCrudController::class, 'Countries', 'lucide:globe');
        yield MenuItem::linkTo(AppOAuthCrudController::class, 'Site socials', 'lucide:megaphone');
        yield MenuItem::linkTo(ParserDataCrudController::class, 'Explorations', 'lucide:eye');
        yield MenuItem::linkTo(ParserHistoryCrudController::class, 'Historique Maj', 'lucide:history');
    }

    /**
     * @param User $user
     */
    #[Override]
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
                'dpr' => 2,
            ]));
    }
}
