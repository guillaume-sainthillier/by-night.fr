<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Admin\Field\VichImageField;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;

#[AdminRoute(path: '/user', name: 'user')]
final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW);
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Membre')
            ->setEntityLabelInPlural('Membres')
            ->setSearchFields([
                'id',
                'email',
                'username',
                'roles',
                'slug',
                'firstname',
                'lastname',
                'description',
                'website',
                'imageHash',
                'imageSystemHash',
                'image.name',
                'image.originalName',
                'image.mimeType',
                'image.size',
                'image.dimensions',
                'imageSystem.name',
                'imageSystem.originalName',
                'imageSystem.mimeType',
                'imageSystem.size',
                'imageSystem.dimensions',
            ]);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addFieldset('Informations');
        $lastLogin = DateTimeField::new('lastLogin');
        $id = IdField::new('id', 'ID');
        $slug = TextField::new('slug');
        $username = TextField::new('username');
        $roles = ArrayField::new('roles');
        $email = TextField::new('email');
        $firstname = TextField::new('firstname');
        $lastname = TextField::new('lastname');
        $description = TextareaField::new('description');
        $oAuth = AssociationField::new('oAuth')->autocomplete();
        $website = TextField::new('website');
        $enabled = BooleanField::new('enabled');
        $isVerified = BooleanField::new('isVerified');
        $panel2 = FormField::addFieldset('Médias');
        $image = VichImageField::new('imageFile', 'Image utilisateur')
            ->setHelp('Photo de profil envoyée par l\'utilisateur, prioritaire sur l\'image système.');
        $imageSystem = VichImageField::new('imageSystemFile', 'Image système')
            ->setHelp('Photo récupérée depuis le réseau social connecté : une prochaine synchronisation peut la remplacer.');
        $imageName = TextField::new('image.name')->onlyOnDetail();
        $imageSystemName = TextField::new('imageSystem.name')->onlyOnDetail();
        $passwordRequestedAt = DateTimeField::new('passwordRequestedAt');
        $fromLogin = BooleanField::new('fromLogin');
        $showSocials = BooleanField::new('showSocials');
        $imageHash = TextField::new('imageHash');
        $imageSystemHash = TextField::new('imageSystemHash');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $imageOriginalName = TextField::new('image.originalName')->onlyOnDetail();
        $imageMimeType = TextField::new('image.mimeType')->onlyOnDetail();
        $imageSize = IntegerField::new('image.size')->onlyOnDetail();
        $imageDimensions = ArrayField::new('image.dimensions')->onlyOnDetail();
        $imageSystemOriginalName = TextField::new('imageSystem.originalName')->onlyOnDetail();
        $imageSystemMimeType = TextField::new('imageSystem.mimeType')->onlyOnDetail();
        $imageSystemSize = IntegerField::new('imageSystem.size')->onlyOnDetail();
        $imageSystemDimensions = ArrayField::new('imageSystem.dimensions')->onlyOnDetail();
        $userEvents = AssociationField::new('userEvents')->autocomplete();
        $city = AssociationField::new('city')->autocomplete();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $username, $email, $lastLogin, $enabled];
        }

        return [
            $panel1,
            $id->hideOnForm(),
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
            $slug,
            $email,
            $username,
            $enabled,
            $lastLogin,
            $passwordRequestedAt,
            $roles,
            $firstname,
            $lastname,
            $description,
            $fromLogin,
            $showSocials,
            $website,
            $isVerified,
            $panel2,
            $image,
            $imageName,
            $imageOriginalName,
            $imageMimeType,
            $imageSize,
            $imageHash,
            $imageDimensions,
            $imageSystem,
            $imageSystemName,
            $imageSystemOriginalName,
            $imageSystemMimeType,
            $imageSystemSize,
            $imageSystemDimensions,
            $imageSystemHash,
            $oAuth,
            $userEvents,
            $city,
        ];
    }
}
