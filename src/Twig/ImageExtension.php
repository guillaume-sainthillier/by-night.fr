<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Dto\EventDto;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\User;
use App\Picture\EventCard;
use App\Picture\EventProfilePicture;
use App\Picture\LocationPicture;
use App\Picture\UserProfilePicture;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;
use Vich\UploaderBundle\Mapping\PropertyMappingFactoryInterface;

final readonly class ImageExtension
{
    public function __construct(
        private EventProfilePicture $eventProfilePicture,
        private UserProfilePicture $userProfilePicture,
        private LocationPicture $locationPicture,
        private PropertyMappingFactoryInterface $propertyMappingFactory,
        private EventCard $eventCard,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The absolute URL of the social image of an event: see EventCard.
     */
    #[AsTwigFunction(name: 'event_card_url')]
    public function eventCardUrl(Event $event): string
    {
        return $this->urlGenerator->generate('app_event_card', ['id' => $event->getId(), 'hash' => $this->eventCard->hash($event)], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The Picasso loader of an upload field, for templates that only hold an entity and a field name (the admin
     * image widgets): each VichUploader mapping has a loader named after it (config/packages/picasso.yaml).
     */
    #[AsTwigFunction(name: 'upload_loader')]
    public function uploadLoader(object $entity, string $field): string
    {
        return $this->propertyMappingFactory->fromField($entity, $field)?->getMappingName()
            ?? throw new InvalidArgumentException(\sprintf('"%s::$%s" is not a VichUploader upload field.', $entity::class, $field));
    }

    /**
     * The picture of a city or country portal, null when it has none.
     *
     * @return array{loader: string|null, src: string|null, context: array{entity?: City|Country, field?: string}}|null
     */
    #[AsTwigFunction(name: 'location_picture')]
    public function locationPicture(City|Country $location): ?array
    {
        return $this->locationPicture->getPicture($location);
    }

    /**
     * @return array{loader: string, src: string|null, context: array{entity: Event|EventDto|null, field: string|null}}
     */
    #[AsTwigFunction(name: 'event_picture')]
    public function eventPicture(Event|EventDto $event): array
    {
        $data = $this->eventProfilePicture->getPicturePathAndSource($event);

        return [
            'loader' => $data['loader'],
            'src' => 'filesystem' === $data['loader'] ? $data['path'] : null,
            'context' => [
                'entity' => $data['entity'],
                'field' => $data['field'],
            ],
        ];
    }

    /**
     * @return array{loader: string|null, src: string|null, unoptimized: bool, context: array{entity: User|null, field: string|null}}
     */
    #[AsTwigFunction(name: 'user_picture')]
    public function userPicture(User $user): array
    {
        $data = $this->userProfilePicture->getPicturePathAndSource($user);

        return [
            'loader' => $data['loader'],
            'src' => 'upload' !== $data['source'] ? $data['path'] : null,
            'unoptimized' => null === $data['loader'],
            'context' => [
                'entity' => $data['entity'],
                'field' => $data['field'],
            ],
        ];
    }
}
