<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Admin\Filter\RegionFilter;
use App\Entity\AdminZone1;
use App\Entity\AdminZone2;
use App\Entity\Place;
use App\Manager\PlaceMerger;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute(path: '/place', name: 'place')]
final class PlaceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PlaceRepository $placeRepository,
        private readonly EventRepository $eventRepository,
        private readonly PlaceMerger $placeMerger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Place::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lieu')
            ->setEntityLabelInPlural('Lieux')
            ->setSearchFields([
                'id',
                'name',
                'metadatas.externalId',
                'metadatas.externalOrigin',
                'cityName',
                'cityPostalCode',
                'facebookId',
                'street',
                'latitude',
                'longitude',
                'slug',
                'path',
                'url',
            ]);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(EntityFilter::new('city')->autocomplete())
            ->add(RegionFilter::new('region', 'Région'))
            ->add(EntityFilter::new('country')->autocomplete());
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $merge = Action::new('mergePlaces', 'Fusionner', 'lucide:merge')
            ->linkToCrudAction('batchMerge')
            ->addCssClass('btn btn-primary');

        return parent::configureActions($actions)
            ->addBatchAction($merge);
    }

    /**
     * The places ticked in the index, to the page that merges them.
     */
    #[AdminRoute(path: '/batch-merge', name: 'batch_merge', options: ['methods' => ['POST']])]
    public function batchMerge(BatchActionDto $batchActionDto): Response
    {
        return $this->redirectToRoute('admin_place_merge', ['ids' => array_values($batchActionDto->getEntityIds())], Response::HTTP_SEE_OTHER);
    }

    /**
     * Shows the places to merge, the one with the most events chosen to take the others, then merges them into the
     * place chosen (PlaceMerger).
     */
    #[AdminRoute(path: '/merge', name: 'merge', options: ['methods' => ['GET', 'POST']])]
    public function merge(Request $request): Response
    {
        $ids = array_values(array_unique(array_map(intval(...), $request->query->all('ids'))));
        $places = [] === $ids ? [] : $this->placeRepository->findBy(['id' => $ids], ['id' => 'ASC']);
        if (\count($places) < 2) {
            $this->addFlash('warning', 'Sélectionnez au moins deux lieux à fusionner.');

            return $this->redirectToRoute('admin_place_index');
        }

        $eventCounts = $this->eventRepository->countByPlaces(array_map(static fn (Place $place): int => (int) $place->getId(), $places));
        $target = $this->placeMerger->suggestTarget($places, $eventCounts);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('place_merge', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $targetId = $request->request->getInt('target');
            $chosen = array_values(array_filter($places, static fn (Place $place): bool => $place->getId() === $targetId));
            if ([] === $chosen) {
                $this->addFlash('danger', 'Choisissez le lieu qui reçoit les autres.');

                return $this->redirectToRoute('admin_place_merge', ['ids' => $ids]);
            }

            $target = $chosen[0];
            $name = (string) $target;
            $result = $this->placeMerger->merge($target, $places);
            $this->addFlash('success', \sprintf(
                '%d lieu(x) fusionné(s) dans %s : %d événement(s), %d identité(s) source et %d slug(s) repris.',
                $result['places'],
                $name,
                $result['events'],
                $result['identities'],
                $result['slugs'],
            ));

            return $this->redirectToRoute('admin_place_detail', ['entityId' => $target->getId()]);
        }

        return $this->render('admin/place/merge.html.twig', [
            'places' => $places,
            'target' => $target,
            'eventCounts' => $eventCounts,
            'regions' => array_map($this->getRegionName(...), $places),
            'ids' => $ids,
        ]);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addFieldset('Informations');
        $id = IdField::new('id', 'ID');
        $externalId = CollectionField::new('metadatas');
        $slug = TextField::new('slug');
        $nom = TextField::new('name');
        $facebookId = TextField::new('facebookId');
        $junk = BooleanField::new('junk');
        $panel2 = FormField::addFieldset('Lieu');
        $rue = TextField::new('street');
        $codePostal = TextField::new('cityPostalCode');
        $ville = TextField::new('cityName');
        $latitude = NumberField::new('latitude');
        $longitude = NumberField::new('longitude');
        $city = AssociationField::new('city')->autocomplete();
        $country = AssociationField::new('country')->autocomplete();
        $path = TextField::new('path');
        $url = TextField::new('url');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $nom, $slug, $city, $country, NumberField::new('upcomingEvents', 'À venir')];
        }

        return [
            $panel1,
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
            $slug,
            $nom,
            $externalId,
            $facebookId,
            $junk,
            $path,
            $url,
            $panel2,
            $rue,
            $codePostal,
            $ville,
            $latitude,
            $longitude,
            $city,
            $country,
        ];
    }

    private function getRegionName(Place $place): ?string
    {
        $zone = $place->getCity()?->getParent();
        if ($zone instanceof AdminZone2) {
            $zone = $zone->getParent();
        }

        return $zone instanceof AdminZone1 ? $zone->getName() : null;
    }
}
