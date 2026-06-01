<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Place;
use App\Entity\PlaceNameSlug;
use App\Repository\PlaceRepository;
use App\Utils\Monitor;
use App\Utils\PaginateTrait;
use App\Utils\PlaceNameNormalizer;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:places:backfill-slugs',
    description: 'Backfill normalized name slugs for existing places (one-time, seeds the de-duplication index)',
)]
final class PlacesBackfillSlugsCommand extends Command
{
    use PaginateTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly PlaceNameNormalizer $placeNameNormalizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Backfilling place name slugs');

        $qb = $this
            ->placeRepository
            ->createQueryBuilder('p')
            ->addSelect('c')
            ->addSelect('co')
            ->leftJoin('p.city', 'c')
            ->leftJoin('p.country', 'co')
            ->orderBy('p.id', Criteria::ASC);

        /** @var PagerfantaInterface<Place> $pagination */
        $pagination = $this->createQueryBuilderPaginator($qb, 1, 500);
        Monitor::createProgressBar($pagination->getNbPages());

        for ($i = 1; $i <= $pagination->getNbPages(); ++$i) {
            $pagination->setCurrentPage($i);

            $places = $pagination->getCurrentPageResults();
            $places = \is_array($places) ? $places : iterator_to_array($places);

            // Hydrate existing slugs in one query to avoid N+1 on hasNameSlug()
            $this->hydrateNameSlugs($places);

            foreach ($places as $place) {
                $slug = $this->placeNameNormalizer->normalize($place->getName(), $place->getCity()?->getName());
                if (null === $slug || $place->hasNameSlug($slug)) {
                    continue;
                }

                $nameSlug = new PlaceNameSlug();
                $nameSlug->setSlug($slug);
                $nameSlug->setCity($place->getCity());
                $nameSlug->setCountry($place->getCountry());
                $place->addNameSlug($nameSlug);
                $this->entityManager->persist($nameSlug);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            Monitor::advanceProgressBar();
        }

        Monitor::finishProgressBar();
        $io->success('Place name slugs backfilled.');

        return Command::SUCCESS;
    }

    /**
     * @param Place[] $places
     */
    private function hydrateNameSlugs(array $places): void
    {
        if ([] === $places) {
            return;
        }

        $ids = array_map(static fn (Place $place) => $place->getId(), $places);
        $this
            ->entityManager
            ->createQueryBuilder()
            ->select('PARTIAL p.{id}')
            ->addSelect('ns')
            ->from(Place::class, 'p')
            ->leftJoin('p.nameSlugs', 'ns')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
