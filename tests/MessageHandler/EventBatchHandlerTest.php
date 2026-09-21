<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\MessageHandler;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\EntityProvider\PlaceEntityProvider;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Handler\ComparatorHandler;
use App\Handler\DoctrineEventHandler;
use App\MessageHandler\EventBatchHandler;
use App\Repository\PlaceMetadataRepository;
use App\Repository\PlaceRepository;
use App\Tests\AppKernelTestCase;
use App\Tests\Handler\StaleLookupPlaceEntityProvider;
use App\Utils\PlaceNameNormalizer;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\Acknowledger;

final class EventBatchHandlerTest extends AppKernelTestCase
{
    /**
     * Two workers import events of the same new venue at the same time: both lookups
     * miss, the first insert wins and the second hits the unique key. The batch must be
     * re-run once (logged as a warning, since it is expected with concurrent workers)
     * and end up attached to the venue the other worker created, with no duplicate.
     */
    public function testBatchIsRerunOnceWhenAConcurrentWorkerWroteTheSameKey(): void
    {
        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);

        $container = self::getContainer();
        $placeProvider = new StaleLookupPlaceEntityProvider(new PlaceEntityProvider(
            $container->get(PlaceRepository::class),
            $container->get(ComparatorHandler::class),
            $container->get(PlaceNameNormalizer::class),
            $container->get(LoggerInterface::class),
        ));
        $container->set(PlaceEntityProvider::class, $placeProvider);

        // Worker 1 imports its batch and commits: the venue now exists
        $container->get(DoctrineEventHandler::class)->handleMany([$this->createEventDto('race-worker-1')]);
        $this->assertSame(1, PlaceFactory::count(['name' => 'Le Bikini']));

        // Worker 2 looked the venue up before worker 1 committed, so its lookups miss
        $placeProvider->missNextLookups();

        $logs = new TestHandler();
        $batchHandler = new EventBatchHandler(
            $container->get(DoctrineEventHandler::class),
            new Logger('test', [$logs]),
            $container->get(ManagerRegistry::class),
        );
        $ack = new Acknowledger(EventBatchHandler::class);
        $batchHandler($this->createEventDto('race-worker-2'), $ack);
        $batchHandler->flush(true);

        $this->assertTrue($ack->isAcknowledged());
        $this->assertNull($ack->getError());
        $this->assertTrue($logs->hasWarningThatContains('concurrent worker'), 'The unique-key collision is expected and logged as a warning');
        $this->assertFalse($logs->hasErrorRecords(), 'The re-run succeeded, no one-by-one fallback was needed');

        // One venue, both events attached to it
        $this->assertSame(1, PlaceFactory::count(['name' => 'Le Bikini']));
        $this->assertSame(1, $container->get(PlaceMetadataRepository::class)->count(['externalId' => 'bikini-42', 'externalOrigin' => 'race-test']));
        $place = PlaceFactory::find(['name' => 'Le Bikini']);
        foreach (['race-worker-1', 'race-worker-2'] as $externalId) {
            $event = EventFactory::find(['externalId' => $externalId, 'externalOrigin' => 'race-test']);
            $this->assertSame($place->getId(), $event->getPlace()?->getId());
        }
    }

    private function createEventDto(string $externalId): EventDto
    {
        $dto = new EventDto();
        $dto->name = \sprintf('Concert %s', $externalId);
        $dto->description = 'Une description suffisamment longue pour passer le firewall sans souci.';
        $dto->startDate = new DateTime('+3 days');
        $dto->endDate = new DateTime('+3 days +2 hours');
        $dto->externalId = $externalId;
        $dto->externalOrigin = 'race-test';
        $dto->parserVersion = '1.0';

        $placeDto = new PlaceDto();
        $placeDto->name = 'Le Bikini';
        $placeDto->street = 'Rue Théodore Monod';
        $placeDto->externalId = 'bikini-42';
        $placeDto->externalOrigin = 'race-test';

        $cityDto = new CityDto();
        $cityDto->name = 'Toulouse';

        $countryDto = new CountryDto();
        $countryDto->code = 'FR';

        $cityDto->country = $countryDto;
        $placeDto->city = $cityDto;
        $placeDto->country = $countryDto;

        $dto->place = $placeDto;

        return $dto;
    }
}
