<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace\Transfer;

use App\Entity\EventExport;
use App\Enum\EventExportScope;
use App\Enum\TransferFormat;
use App\Form\Type\EventExportType;
use App\Message\RunEventExport;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Security\Voter\ExportVoter;
use App\Transfer\TransferFiles;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A member's export of events: started from its page, followed live in the page's history (read again here,
 * history(), when an export changes), then downloaded.
 */
final class ExportController extends AbstractTransferController
{
    #[Route(path: '/exports', name: 'app_event_exports', methods: ['GET'])]
    public function index(EventExportRepository $exportRepository): Response
    {
        return $this->render('personal-space/transfers/export/index.html.twig', [
            'form' => $this->createForm(EventExportType::class, null, ['action' => $this->generateUrl('app_event_export_new')]),
            'exports' => $exportRepository->findLatestByUser($this->getAppUser()),
        ]);
    }

    /**
     * The member's latest exports, which the page reads again when one of them changes.
     */
    #[Route(path: '/exports/historique', name: 'app_event_exports_history', methods: ['GET'])]
    public function history(EventExportRepository $exportRepository): Response
    {
        return $this->render('personal-space/transfers/export/_history.html.twig', [
            'exports' => $exportRepository->findLatestByUser($this->getAppUser()),
        ]);
    }

    #[Route(path: '/exports', name: 'app_event_export_new', methods: ['POST'])]
    public function new(Request $request, EventImportRepository $importRepository, EventExportRepository $exportRepository, MessageBusInterface $bus): Response
    {
        $user = $this->getAppUser();
        $form = $this->createForm(EventExportType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->formErrors($form) ?: 'L’export n’a pas pu être lancé.');

            return $this->redirectToRoute('app_event_exports');
        }

        if (!$this->hasNoJobRunning($user, $importRepository, $exportRepository)) {
            return $this->redirectToRoute('app_event_exports');
        }

        /** @var EventExportScope $scope */
        $scope = $form->get('scope')->getData();
        /** @var TransferFormat $format */
        $format = $form->get('format')->getData();
        $export = new EventExport()->setUser($user)->setScope($scope)->setFormat($format);
        $em = $this->getEntityManager();
        $em->persist($export);
        $em->flush();

        $bus->dispatch(new RunEventExport((int) $export->getId()));
        $this->addFlash('success', 'Votre export est lancé : il sera prêt au téléchargement dans un instant, ci-dessous.');

        return $this->redirectToRoute('app_event_exports', ['_fragment' => 'historique']);
    }

    /**
     * The export as it stands (its row of the history): the IRI of its live updates.
     */
    #[Route(path: '/exports/{id<%patterns.id%>}', name: 'app_event_export_show', methods: ['GET'])]
    #[IsGranted(ExportVoter::VIEW, subject: 'export')]
    public function show(EventExport $export): Response
    {
        return $this->render('personal-space/transfers/export/_row.html.twig', [
            'export' => $export,
        ]);
    }

    #[Route(path: '/exports/{id<%patterns.id%>}/fichier', name: 'app_event_export_file', methods: ['GET'])]
    #[IsGranted(ExportVoter::VIEW, subject: 'export')]
    public function downloadFile(EventExport $export, TransferFiles $files): Response
    {
        return $this->download($files, $export->getFilePath(), $export->getFormat(), $export->getDownloadName());
    }
}
