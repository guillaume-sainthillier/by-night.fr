<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Admin\Filter\UserWithEventFilter;
use App\Entity\Event;
use App\Form\Type\EventTimesheetEntityType;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
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
use Override;

#[AdminRoute(path: '/event', name: 'event')]
final class EventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Event')
            ->setEntityLabelInPlural('Events')
            ->setSearchFields([
                'id',
                'externalId',
                'slug',
                'name',
                'description',
                'address',
                'type',
                'category',
                'theme',
                'phoneContacts',
                'mailContacts',
                'websiteContacts',
                'fromData',
                'name',
                'url',
                'facebookEventId',
                'facebookOwnerId',
                'source',
                'placeName',
                'placeStreet',
                'placeCity',
                'placePostalCode',
                'placeExternalId',
                'placeFacebookId',
                'image.name',
                'imageSystem.name',
            ]);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user'))
            ->add(UserWithEventFilter::new('WithUser'));
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addPanel('Informations');
        $id = IdField::new('id', 'ID');
        $user = AssociationField::new('user')->autocomplete();
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $externalId = TextField::new('externalId');
        $externalOrigin = TextField::new('externalOrigin');
        $slug = TextField::new('slug');
        $nom = TextField::new('name');
        $startDate = DateField::new('startDate');
        $endDate = DateField::new('endDate');
        $horaires = TextField::new('hours');
        $timesheets = CollectionField::new('timesheets')
            ->setEntryType(EventTimesheetEntityType::class)
            ->allowAdd()
            ->allowDelete()
            ->setLabel('Dates et horaires');
        $descriptif = TextareaField::new('description');
        $externalUpdatedAt = DateTimeField::new('externalUpdatedAt');
        $status = TextField::new('status');
        $type = TextField::new('type');
        $category = TextField::new('category');
        $theme = TextField::new('theme');
        $phoneContacts = CollectionField::new('phoneContacts');
        $mailContacts = CollectionField::new('mailContacts');
        $websiteContacts = CollectionField::new('websiteContacts');
        $tarif = TextField::new('prices');
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
        $adresse = TextField::new('address');
        $panel3 = FormField::addPanel('Images');
        $url = TextField::new('url');
        $imageName = TextField::new('image.name');
        $imageSystemName = TextField::new('imageSystem.name');
        $panel4 = FormField::addPanel('Parser');
        $duplicateOf = AssociationField::new('duplicateOf')
            ->setLabel('Duplicate de (redirige vers)')
            ->autocomplete()
            ->setHelp('Si défini, cet événement redirigera vers l\'événement principal');
        $fromData = TextField::new('fromData');
        $parserVersion = TextField::new('parserVersion');
        $source = TextField::new('source');
        $imageHash = TextField::new('imageHash');
        $imageSystemHash = TextField::new('imageSystemHash');
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
        }

        return [
            $panel1,
            $id->hideOnForm(),
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
            $slug,
            $nom,
            $descriptif,
            $startDate,
            $endDate,
            $horaires,
            $timesheets,
            $tarif,
            $status,
            $latitude,
            $longitude,
            $type,
            $category,
            $theme,
            $phoneContacts,
            $mailContacts,
            $websiteContacts,
            $draft,
            $tweetPostId,
            $facebookEventId,
            $tweetPostSystemId,
            $fbPostId,
            $fbPostSystemId,
            $facebookOwnerId,
            $fbParticipations,
            $fbInterets,
            $participations,
            $interets,
            $source,
            $archive,

            $panel2,
            $place,
            $placeCountry,
            $adresse,
            $placeName,
            $placeStreet,
            $placeCity,
            $placePostalCode,
            $placeExternalId,
            $placeFacebookId,

            $panel3,
            $url,
            $imageName,
            $imageOriginalName,
            $imageMimeType,
            $imageSize,
            $imageHash,
            $imageDimensions,
            $imageSystemName,
            $imageSystemOriginalName,
            $imageSystemMimeType,
            $imageSystemSize,
            $imageSystemHash,
            $imageSystemDimensions,

            $panel4,
            $duplicateOf,
            $externalId,
            $externalOrigin,
            $externalUpdatedAt,
            $user,
            $userEvents,
            $comments,
            $fromData,
            $parserVersion,
        ];
    }
}
