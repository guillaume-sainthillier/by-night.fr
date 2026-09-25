<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EntityProvider;

use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\PlaceMetadata;
use App\EntityProvider\EventEntityProvider;
use App\EntityProvider\PlaceEntityProvider;
use App\EntityProvider\TagEntityProvider;
use App\Factory\EventFactory;
use App\Factory\TagFactory;
use App\Tests\AppKernelTestCase;

/**
 * A provider files an entity under its keys and hands it to the DTO sharing one: the DTO
 * and the entity must spell their keys the same way (AbstractEntityProvider::getObjectKeys()).
 */
final class EntityProviderKeysTest extends AppKernelTestCase
{
    public function testAnEventIsFoundByItsSourceIdOrItsDatabaseId(): void
    {
        $event = EventFactory::createOne(['externalOrigin' => 'openagenda', 'externalId' => '42']);
        $provider = self::getContainer()->get(EventEntityProvider::class);
        $provider->clear();
        $provider->addEntity($event);

        self::assertSame($event, $provider->getEntity($this->event('openagenda', '42')));
        self::assertNull($provider->getEntity($this->event('datatourisme', '42')), 'Another source numbers its own events');

        $resolved = new EventDto();
        $resolved->entityId = $event->getId();
        self::assertSame($event, $provider->getEntity($resolved));
    }

    public function testAPlaceIsFoundByAnyOfItsSourceIds(): void
    {
        $place = new Place()
            ->setName('Le Bikini')
            ->addMetadata(new PlaceMetadata()->setExternalOrigin('openagenda')->setExternalId('7'))
            ->addMetadata(new PlaceMetadata()->setExternalOrigin('datatourisme')->setExternalId('PCU-7'));
        $provider = self::getContainer()->get(PlaceEntityProvider::class);
        $provider->clear();
        $provider->addEntity($place);

        self::assertSame($place, $provider->getEntity($this->place('openagenda', '7')));
        self::assertSame($place, $provider->getEntity($this->place('datatourisme', 'PCU-7')));
    }

    public function testATagIsFoundByItsNameWhateverItsCaseAndAccents(): void
    {
        $tag = TagFactory::createOne(['name' => 'Théâtre']);
        $provider = self::getContainer()->get(TagEntityProvider::class);
        $provider->clear();
        $provider->addEntity($tag);

        self::assertSame($tag, $provider->getEntity(TagDto::fromString('THEATRE')));
        self::assertSame($tag, $provider->getEntity(TagDto::fromEntity($tag)));
        self::assertNull($provider->getEntity(TagDto::fromString('Théâtre de rue')));
    }

    public function testANewEntityIsFoundByTheDtoItWasCreatedFrom(): void
    {
        $dto = new EventDto();
        $event = new Event();
        $provider = self::getContainer()->get(EventEntityProvider::class);
        $provider->clear();
        $provider->addEntity($event, $dto);

        self::assertSame($event, $provider->getEntity($dto));
        self::assertNull($provider->getEntity(new EventDto()), 'Nothing identifies another DTO without id');
    }

    private function event(string $origin, string $id): EventDto
    {
        $dto = new EventDto();
        $dto->externalOrigin = $origin;
        $dto->externalId = $id;

        return $dto;
    }

    private function place(string $origin, string $id): PlaceDto
    {
        $dto = new PlaceDto();
        $dto->externalOrigin = $origin;
        $dto->externalId = $id;

        return $dto;
    }
}
