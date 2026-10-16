<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\Controller\AbstractController as BaseController;
use App\Entity\EventExport;
use App\Entity\EventImport;
use App\Entity\User;
use App\Enum\EventExportScope;
use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Form\Type\EventExportType;
use App\Form\Type\EventImportType;
use App\Message\CheckEventImport;
use App\Message\RunEventExport;
use App\Message\RunEventImport;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Security\Voter\EventVoter;
use App\Security\Voter\TransferVoter;
use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\EventSheetTemplate;
use App\Transfer\TransferFiles;
use App\Transfer\TransferNotifier;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The bulk import and export of a member's events. The jobs run on a worker (the "transfers" transport); their
 * pages follow them live through the member's Mercure channel and read their state again here (the status
 * fragments), when a job tells them it changed or when they reconnect.
 */
final class TransferController extends BaseController
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

    #[Route(path: '/import-export/modele.{format<csv|xlsx>}', name: 'app_event_import_template', methods: ['GET'])]
    public function template(string $format, EventSheetTemplate $template, TransferFiles $files): Response
    {
        $format = TransferFormat::from($format);
        $local = $files->createLocalFile($format);
        $template->write($local, $format);

        return new BinaryFileResponse($local, headers: ['Content-Type' => $format->getMimeType()])
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'modele-import-by-night.' . $format->value)
            ->deleteFileAfterSend();
    }

    #[Route(path: '/imports', name: 'app_event_import_new', methods: ['POST'])]
    #[IsGranted(EventVoter::CREATE)]
    public function newImport(Request $request, EventImportRepository $importRepository, EventExportRepository $exportRepository, TransferFiles $files, MessageBusInterface $bus): Response
    {
        $user = $this->getAppUser();
        $form = $this->createForm(EventImportType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->formErrors($form) ?: 'Le fichier n’a pas pu être envoyé.');

            return $this->redirectToRoute('app_event_transfers');
        }

        if (!$this->hasNoJobRunning($user, $importRepository, $exportRepository)) {
            return $this->redirectToRoute('app_event_transfers');
        }

        /** @var UploadedFile $file */
        $file = $form->get('file')->getData();
        $format = TransferFormat::fromFilename($file->getClientOriginalName()) ?? TransferFormat::Csv;
        // A directory of its own, which its report files join (EventImportChecker)
        $path = \sprintf('imports/%s/original.%s', bin2hex(random_bytes(8)), $format->value);
        $files->upload($file->getPathname(), $path);

        $import = new EventImport()
            ->setUser($user)
            ->setFormat($format)
            ->setOriginalName(mb_substr($file->getClientOriginalName(), 0, 255))
            ->setFilePath($path);
        $em = $this->getEntityManager();
        $em->persist($import);
        $em->flush();

        $bus->dispatch(new CheckEventImport((int) $import->getId()));

        return $this->redirectToRoute('app_event_import_show', ['id' => $import->getId()]);
    }

    #[Route(path: '/imports/{id<%patterns.id%>}', name: 'app_event_import_show', methods: ['GET'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'import')]
    public function showImport(EventImport $import): Response
    {
        return $this->render('personal-space/transfers/import.html.twig', [
            'import' => $import,
        ]);
    }

    /**
     * The import's report as it stands, which its page reads again when the import tells it it changed.
     */
    #[Route(path: '/imports/{id<%patterns.id%>}/statut', name: 'app_event_import_status', methods: ['GET'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'import')]
    public function importStatus(EventImport $import): Response
    {
        return $this->render('personal-space/transfers/_import_status.html.twig', [
            'import' => $import,
        ]);
    }

    /**
     * Imports the events the check kept, or resumes an import that stopped halfway.
     */
    #[Route(path: '/imports/{id<%patterns.id%>}/importer', name: 'app_event_import_run', methods: ['POST'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'import')]
    #[IsGranted(EventVoter::CREATE)]
    public function runImport(Request $request, EventImport $import, EventImportRepository $importRepository, MessageBusInterface $bus): Response
    {
        $this->denyUnlessValidToken($request, 'event-import-run-' . $import->getId());

        if (0 === $import->getValidCount()) {
            $this->addFlash('error', 'Aucun événement valide à importer : corrigez le fichier des erreurs, puis importez-le.');
        } elseif ($importRepository->transition($import, [EventImportStatus::Checked, EventImportStatus::Failed], EventImportStatus::Importing)) {
            $bus->dispatch(new RunEventImport((int) $import->getId()));
        }

        return $this->redirectToRoute('app_event_import_show', ['id' => $import->getId()]);
    }

    /**
     * Stops a check: nothing was written, the import can be started over with another file.
     */
    #[Route(path: '/imports/{id<%patterns.id%>}/annuler', name: 'app_event_import_cancel', methods: ['POST'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'import')]
    public function cancelImport(Request $request, EventImport $import, EventImportRepository $importRepository, TransferNotifier $notifier): Response
    {
        $this->denyUnlessValidToken($request, 'event-import-cancel-' . $import->getId());

        if ($importRepository->transition($import, [EventImportStatus::Uploaded, EventImportStatus::Checking, EventImportStatus::Checked], EventImportStatus::Cancelled)) {
            $this->getEntityManager()->refresh($import);
            $notifier->importChanged($import);
        }

        return $this->redirectToRoute('app_event_transfers');
    }

    #[Route(path: '/imports/{id<%patterns.id%>}/fichiers/{kind<original|valides|erreurs>}', name: 'app_event_import_file', methods: ['GET'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'import')]
    public function importFile(EventImport $import, string $kind, TransferFiles $files): Response
    {
        $path = match ($kind) {
            'original' => $import->getFilePath(),
            'valides' => $import->getSuccessFilePath(),
            default => $import->getErrorFilePath(),
        };
        $name = pathinfo((string) $import->getOriginalName(), \PATHINFO_FILENAME);

        return $this->download($files, $path, $import->getFormat(), 'original' === $kind ? (string) $import->getOriginalName() : \sprintf('%s-%s.%s', $name, $kind, $import->getFormat()?->value));
    }

    #[Route(path: '/exports', name: 'app_event_export_new', methods: ['POST'])]
    public function newExport(Request $request, EventImportRepository $importRepository, EventExportRepository $exportRepository, MessageBusInterface $bus): Response
    {
        $user = $this->getAppUser();
        $form = $this->createForm(EventExportType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->formErrors($form) ?: 'L’export n’a pas pu être lancé.');

            return $this->redirectToRoute('app_event_transfers');
        }

        if (!$this->hasNoJobRunning($user, $importRepository, $exportRepository)) {
            return $this->redirectToRoute('app_event_transfers');
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

        return $this->redirectToRoute('app_event_transfers', ['_fragment' => 'historique']);
    }

    /**
     * The export as it stands (its row of the history): the IRI of its live updates.
     */
    #[Route(path: '/exports/{id<%patterns.id%>}', name: 'app_event_export_show', methods: ['GET'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'export')]
    public function showExport(EventExport $export): Response
    {
        return $this->render('personal-space/transfers/_export_row.html.twig', [
            'export' => $export,
        ]);
    }

    #[Route(path: '/exports/{id<%patterns.id%>}/fichier', name: 'app_event_export_file', methods: ['GET'])]
    #[IsGranted(TransferVoter::VIEW, subject: 'export')]
    public function exportFile(EventExport $export, TransferFiles $files): Response
    {
        return $this->download($files, $export->getFilePath(), $export->getFormat(), $export->getDownloadName());
    }

    private function download(TransferFiles $files, ?string $path, ?TransferFormat $format, string $name): Response
    {
        if (null === $path || null === $format || !$files->exists($path)) {
            throw $this->createNotFoundException('This file is gone.');
        }

        $response = new StreamedResponse(static function () use ($files, $path): void {
            $stream = $files->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, ['Content-Type' => $format->getMimeType()]);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name, 'export.' . $format->value));

        return $response;
    }

    /**
     * One job at a time per member: a second one waits for the first to finish.
     */
    private function hasNoJobRunning(User $user, EventImportRepository $importRepository, EventExportRepository $exportRepository): bool
    {
        $running = $importRepository->countRunningByUser($user) + $exportRepository->countRunningByUser($user);
        if (0 < $running) {
            $this->addFlash('warning', 'Un import ou un export est déjà en cours : attendez qu’il se termine pour en lancer un autre.');
        }

        return 0 === $running;
    }

    private function denyUnlessValidToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function formErrors(FormInterface $form): string
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }

        return implode(' ', $messages);
    }
}
