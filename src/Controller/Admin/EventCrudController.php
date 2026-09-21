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
use App\Admin\Filter\FromDataFilter;
use App\Admin\Filter\UserWithEventFilter;
use App\Entity\Event;
use App\Enum\EventStatus;
use App\Form\Type\EventTimesheetEntityType;
use App\Repository\EventRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

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
                'category.name',
                'themes.name',
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
            ->add(UserWithEventFilter::new('WithUser'))
            ->add(FromDataFilter::new('fromData', $this->getFromDataChoices(...), 'Source'));
    }

    /**
     * Choices of the "Source" filter, as label => fromData value.
     *
     * @return array<string, string>
     */
    private function getFromDataChoices(): array
    {
        // Read from the events rather than the parsers: sources of removed parsers (Facebook, SoonNight, ...) still own a third of them
        $sources = $this->eventRepository->findDistinctFromData();

        return array_combine($sources, $sources);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addFieldset('Informations');
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
        $status = ChoiceField::new('status')
            ->setChoices(EventStatus::cases())
            ->renderAsBadges([
                EventStatus::Postponed->value => 'warning',
                EventStatus::Cancelled->value => 'danger',
                EventStatus::SoldOut->value => 'info',
            ]);
        $statusMessage = TextField::new('statusMessage');
        $type = TextField::new('type');
        $category = AssociationField::new('category')
            ->setCrudController(TagCrudController::class)
            ->autocomplete();
        $themes = AssociationField::new('themes')
            ->setCrudController(TagCrudController::class)
            ->autocomplete();
        $phoneContacts = CollectionField::new('phoneContacts');
        $mailContacts = CollectionField::new('mailContacts');
        $websiteContacts = CollectionField::new('websiteContacts');
        $tarif = TextField::new('prices');
        $draft = BooleanField::new('draft');
        $archive = BooleanField::new('archive');
        $panel2 = FormField::addFieldset('Lieu');
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
        $systemImagePanel = FormField::addFieldset('Image système');
        $imageSystem = VichImageField::new('imageSystemFile', 'Image')
            ->setHelp('Récupérée à l\'import : un nouvel import peut la remplacer. Pour imposer une image, utilisez l\'image utilisateur.');
        $url = TextField::new('url', 'URL source')
            ->setHelp('Image téléchargée à l\'import.');
        $userImagePanel = FormField::addFieldset('Image utilisateur');
        $image = VichImageField::new('imageFile', 'Image')
            ->setHelp('Envoyée par l\'utilisateur, prioritaire sur l\'image système à l\'affichage.');
        $imageName = TextField::new('image.name')->onlyOnDetail();
        $imageSystemName = TextField::new('imageSystem.name')->onlyOnDetail();
        $panel4 = FormField::addFieldset('Parser');
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
        $fbInterests = IntegerField::new('fbInterests');
        $participations = IntegerField::new('participations');
        $interests = IntegerField::new('interests');
        $imageOriginalName = TextField::new('image.originalName')->onlyOnDetail();
        $imageMimeType = TextField::new('image.mimeType')->onlyOnDetail();
        $imageSize = IntegerField::new('image.size')->onlyOnDetail();
        $imageDimensions = ArrayField::new('image.dimensions')->onlyOnDetail();
        $imageSystemOriginalName = TextField::new('imageSystem.originalName')->onlyOnDetail();
        $imageSystemMimeType = TextField::new('imageSystem.mimeType')->onlyOnDetail();
        $imageSystemSize = IntegerField::new('imageSystem.size')->onlyOnDetail();
        $imageSystemDimensions = ArrayField::new('imageSystem.dimensions')->onlyOnDetail();
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
            $statusMessage,
            $latitude,
            $longitude,
            $type,
            $category,
            $themes,
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
            $fbInterests,
            $participations,
            $interests,
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

            $systemImagePanel,
            $imageSystem,
            $url,
            $imageSystemHash,
            $imageSystemName,
            $imageSystemOriginalName,
            $imageSystemMimeType,
            $imageSystemSize,
            $imageSystemDimensions,

            $userImagePanel,
            $image,
            $imageHash,
            $imageName,
            $imageOriginalName,
            $imageMimeType,
            $imageSize,
            $imageDimensions,

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
