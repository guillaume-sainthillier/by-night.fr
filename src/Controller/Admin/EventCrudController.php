<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Admin\Filter\UserWithEventFilter;
use App\Entity\Event;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use RuntimeException;

class EventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Event')
            ->setEntityLabelInPlural('Events')
            ->setSearchFields(['id', 'externalId', 'slug', 'nom', 'descriptif', 'adresse', 'type', 'category', 'theme', 'phoneContacts', 'mailContacts', 'websiteContacts', 'fromData', 'name', 'url', 'facebookEventId', 'facebookOwnerId', 'source', 'placeName', 'placeStreet', 'placeCity', 'placePostalCode', 'placeExternalId', 'placeFacebookId', 'image.name', 'imageSystem.name']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user'))
            ->add(UserWithEventFilter::new('WithUser'));
    }

    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addPanel('Informations');
        $id = IdField::new('id', 'ID');
        $user = AssociationField::new('user')->autocomplete();
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $externalId = TextField::new('externalId');
        $slug = TextField::new('slug');
        $nom = TextField::new('nom');
        $startDate = DateField::new('startDate');
        $endDate = DateField::new('endDate');
        $horaires = TextField::new('horaires');
        $descriptif = TextareaField::new('descriptif');
        $externalUpdatedAt = DateTimeField::new('externalUpdatedAt');
        $status = TextField::new('status');
        $type = TextField::new('type');
        $category = TextField::new('category');
        $theme = TextField::new('theme');
        $phoneContacts = CollectionField::new('phoneContacts');
        $mailContacts = CollectionField::new('mailContacts');
        $websiteContacts = CollectionField::new('websiteContacts');
        $tarif = TextField::new('tarif');
        $draft = BooleanField::new('draft');
        $archive = BooleanField::new('archive');
        $panel2 = FormField::addPanel('Lieu');
        $place = AssociationField::new('place')->autocomplete();
        $placeName = TextField::new('placeName');
        $placeStreet = TextField::new('placeStreet');
        $placeCity = TextField::new('placeCity');
        $placePostalCode = TextField::new('placePostalCode');
        $placeExternalId = TextField::new('placeExternalId');
        $placeFacebookId = TextField::new('placeFacebookId');
        $placeCountry = AssociationField::new('placeCountry');
        $latitude = NumberField::new('latitude');
        $longitude = NumberField::new('longitude');
        $adresse = TextField::new('adresse');
        $panel3 = FormField::addPanel('Images');
        $url = TextField::new('url');
        $imageName = TextField::new('image.name');
        $imageSystemName = TextField::new('imageSystem.name');
        $panel4 = FormField::addPanel('Parser');
        $fromData = TextField::new('fromData');
        $parserVersion = TextField::new('parserVersion');
        $source = TextField::new('source');
        $imageHash = TextField::new('imageHash');
        $imageSystemHash = TextField::new('imageSystemHash');
        $name = TextField::new('name');
        $tweetPostId = TextField::new('tweetPostId');
        $facebookEventId = TextField::new('facebookEventId');
        $tweetPostSystemId = TextField::new('tweetPostSystemId');
        $fbPostId = TextField::new('fbPostId');
        $fbPostSystemId = TextField::new('fbPostSystemId');
        $facebookOwnerId = TextField::new('facebookOwnerId');
        $fbParticipations = IntegerField::new('fbParticipations');
        $fbInterets = IntegerField::new('fbInterets');
        $participations = IntegerField::new('participations');
        $interets = IntegerField::new('interets');
        $imageOriginalName = TextField::new('image.originalName');
        $imageMimeType = TextField::new('image.mimeType');
        $imageSize = IntegerField::new('image.size');
        $imageDimensions = ArrayField::new('image.dimensions');
        $imageSystemOriginalName = TextField::new('imageSystem.originalName');
        $imageSystemMimeType = TextField::new('imageSystem.mimeType');
        $imageSystemSize = IntegerField::new('imageSystem.size');
        $imageSystemDimensions = ArrayField::new('imageSystem.dimensions');
        $userEvents = AssociationField::new('userEvents')->autocomplete();
        $comments = AssociationField::new('comments')->autocomplete();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $createdAt, $updatedAt, $fromData, $nom, $place, $user];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $externalId, $slug, $nom, $descriptif, $externalUpdatedAt, $startDate, $endDate, $horaires, $status, $latitude, $longitude, $adresse, $type, $category, $theme, $phoneContacts, $mailContacts, $websiteContacts, $tarif, $fromData, $parserVersion, $imageHash, $imageSystemHash, $name, $url, $draft, $tweetPostId, $facebookEventId, $tweetPostSystemId, $fbPostId, $fbPostSystemId, $facebookOwnerId, $fbParticipations, $fbInterets, $participations, $interets, $source, $archive, $placeName, $placeStreet, $placeCity, $placePostalCode, $placeExternalId, $placeFacebookId, $createdAt, $updatedAt, $imageName, $imageOriginalName, $imageMimeType, $imageSize, $imageDimensions, $imageSystemName, $imageSystemOriginalName, $imageSystemMimeType, $imageSystemSize, $imageSystemDimensions, $user, $userEvents, $comments, $place, $placeCountry];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$panel1, $user, $externalId, $nom, $startDate, $endDate, $horaires, $descriptif, $status, $type, $category, $theme, $phoneContacts, $mailContacts, $websiteContacts, $tarif, $draft, $archive, $panel2, $place, $placeName, $placeStreet, $placeCity, $placePostalCode, $placeExternalId, $placeFacebookId, $placeCountry, $latitude, $longitude, $adresse, $panel3, $url, $imageName, $imageSystemName, $panel4, $fromData, $parserVersion, $source];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$panel1, $user, $createdAt, $updatedAt, $externalId, $slug, $nom, $startDate, $endDate, $horaires, $descriptif, $externalUpdatedAt, $status, $type, $category, $theme, $phoneContacts, $mailContacts, $websiteContacts, $tarif, $draft, $archive, $panel2, $place, $placeName, $placeStreet, $placeCity, $placePostalCode, $placeExternalId, $placeFacebookId, $placeCountry, $latitude, $longitude, $adresse, $panel3, $url, $imageName, $imageSystemName, $panel4, $fromData, $parserVersion, $source];
        }

        throw new RuntimeException(sprintf('Unable to configure fields for page "%s"', $pageName));
    }
}
