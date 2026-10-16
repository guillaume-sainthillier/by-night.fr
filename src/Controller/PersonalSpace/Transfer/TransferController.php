<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace\Transfer;

use App\Form\Type\EventExportType;
use App\Form\Type\EventImportType;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Transfer\Sheet\EventColumn;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page of a member's imports and exports: their forms and their latest jobs, which follow them live through the
 * member's Mercure channel and read the history again (history()) when one of them changes.
 */
final class TransferController extends AbstractTransferController
{
    #[Route(path: '/import-export', name: 'app_event_transfers', methods: ['GET'])]
    public function index(EventImportRepository $importRepository, EventExportRepository $exportRepository): Response
    {
        $user = $this->getAppUser();

        return $this->render('personal-space/transfers/index.html.twig', [
            'import_form' => $this->createForm(EventImportType::class, null, ['action' => $this->generateUrl('app_event_import_new')]),
            'export_form' => $this->createForm(EventExportType::class, null, ['action' => $this->generateUrl('app_event_export_new')]),
            'imports' => $importRepository->findLatestByUser($user),
            'exports' => $exportRepository->findLatestByUser($user),
            'columns' => EventColumn::cases(),
        ]);
    }

    /**
     * The member's latest imports and exports, which the page reads again when one of them changes.
     */
    #[Route(path: '/import-export/historique', name: 'app_event_transfers_history', methods: ['GET'])]
    public function history(EventImportRepository $importRepository, EventExportRepository $exportRepository): Response
    {
        $user = $this->getAppUser();

        return $this->render('personal-space/transfers/_history.html.twig', [
            'imports' => $importRepository->findLatestByUser($user),
            'exports' => $exportRepository->findLatestByUser($user),
        ]);
    }
}
