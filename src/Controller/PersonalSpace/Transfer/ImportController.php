<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace\Transfer;

use App\Entity\EventImport;
use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Form\Type\EventImportType;
use App\Message\CheckEventImport;
use App\Message\RunEventImport;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Security\Voter\EventVoter;
use App\Security\Voter\ImportVoter;
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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A publisher's import of events: the page with the template and the upload, then each import's report, which follows
 * the check and the import live and reads its state again here (status()) when the import tells it it changed or
 * when it reconnects.
 */
#[Route(path: '/organisateurs/{publisher<%patterns.slug%>}')]
final class ImportController extends AbstractTransferController
{
    #[Route(path: '/imports', name: 'app_event_imports', methods: ['GET'])]
    public function index(EventImportRepository $importRepository): Response
    {
        return $this->render('personal-space/transfers/import/index.html.twig', [
            'form' => $this->getCreateForm(),
            'imports' => $importRepository->findLatestByPublisher($this->publisherContext->get()),
            'columns' => EventColumn::cases(),
        ]);
    }

    /**
     * The member's latest imports, which the page reads again when one of them changes.
     */
    #[Route(path: '/imports/historique', name: 'app_event_imports_history', methods: ['GET'])]
    public function history(EventImportRepository $importRepository): Response
    {
        return $this->render('personal-space/transfers/import/_history.html.twig', [
            'imports' => $importRepository->findLatestByPublisher($this->publisherContext->get()),
        ]);
    }

    #[Route(path: '/imports/modele.{format<csv|xlsx>}', name: 'app_event_import_template', methods: ['GET'])]
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
    public function new(Request $request, EventImportRepository $importRepository, EventExportRepository $exportRepository, TransferFiles $files, MessageBusInterface $bus): Response
    {
        $user = $this->getAppUser();
        $form = $this->createForm(EventImportType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->formErrors($form) ?: 'Le fichier n’a pas pu être envoyé.');

            return $this->redirectToRoute('app_event_imports');
        }

        if (!$this->hasNoJobRunning($user, $importRepository, $exportRepository)) {
            return $this->redirectToRoute('app_event_imports');
        }

        /** @var UploadedFile $file */
        $file = $form->get('file')->getData();
        $format = TransferFormat::fromFilename($file->getClientOriginalName()) ?? TransferFormat::Csv;
        // A directory of its own, which its report files join (EventImportChecker)
        $path = \sprintf('imports/%s/original.%s', bin2hex(random_bytes(8)), $format->value);
        $files->upload($file->getPathname(), $path);

        $import = new EventImport()
            ->setUser($user)
            ->setPublisher($this->publisherContext->get())
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
    #[IsGranted(ImportVoter::VIEW, subject: 'import')]
    public function show(EventImport $import): Response
    {
        $this->denyUnlessInContext($import);

        return $this->render('personal-space/transfers/import/show.html.twig', [
            'import' => $import,
            'form' => $this->getCreateForm(),
        ]);
    }

    /**
     * The import's report as it stands, which its page reads again when the import tells it it changed.
     */
    #[Route(path: '/imports/{id<%patterns.id%>}/statut', name: 'app_event_import_status', methods: ['GET'])]
    #[IsGranted(ImportVoter::VIEW, subject: 'import')]
    public function status(EventImport $import): Response
    {
        $this->denyUnlessInContext($import);

        return $this->render('personal-space/transfers/import/_status.html.twig', [
            'import' => $import,
            'form' => $this->getCreateForm(),
        ]);
    }

    /**
     * Imports the events the check kept, or resumes an import that stopped halfway.
     */
    #[Route(path: '/imports/{id<%patterns.id%>}/importer', name: 'app_event_import_run', methods: ['POST'])]
    #[IsGranted(ImportVoter::VIEW, subject: 'import')]
    #[IsGranted(EventVoter::CREATE)]
    public function run(Request $request, EventImport $import, EventImportRepository $importRepository, MessageBusInterface $bus): Response
    {
        $this->denyUnlessInContext($import);
        $this->denyUnlessValidToken($request, 'event-import-run-' . $import->getId());

        // A first run, not a resumed one: a resumed import keeps the choice it started with
        $firstRun = EventImportStatus::Checked === $import->getStatus();
        if (0 === $import->getValidCount()) {
            $this->addFlash('error', 'Aucun événement valide à importer : corrigez le fichier des erreurs, puis importez-le.');
        } elseif ($importRepository->transition($import, EventImportStatus::Importing)) {
            // The status moved in the database (a conditional UPDATE): read it back before writing anything else
            $this->getEntityManager()->refresh($import);
            if ($firstRun) {
                // The warned events the member unticked, by their reference
                $import->setSkippedReferences(array_values(array_filter($request->getPayload()->all('skip'), is_string(...))));
                $this->getEntityManager()->flush();
            }

            $bus->dispatch(new RunEventImport((int) $import->getId()));
        }

        return $this->redirectToRoute('app_event_import_show', ['id' => $import->getId()]);
    }

    /**
     * Stops a check: nothing was written, the import can be started over with another file.
     */
    #[Route(path: '/imports/{id<%patterns.id%>}/annuler', name: 'app_event_import_cancel', methods: ['POST'])]
    #[IsGranted(ImportVoter::VIEW, subject: 'import')]
    public function cancel(Request $request, EventImport $import, EventImportRepository $importRepository, TransferNotifier $notifier): Response
    {
        $this->denyUnlessInContext($import);
        $this->denyUnlessValidToken($request, 'event-import-cancel-' . $import->getId());

        if ($importRepository->transition($import, EventImportStatus::Cancelled)) {
            $this->getEntityManager()->refresh($import);
            $notifier->importChanged($import);
        }

        return $this->redirectToRoute('app_event_imports');
    }

    #[Route(path: '/imports/{id<%patterns.id%>}/fichiers/{kind<original|valides|erreurs>}', name: 'app_event_import_file', methods: ['GET'])]
    #[IsGranted(ImportVoter::VIEW, subject: 'import')]
    public function downloadFile(EventImport $import, string $kind, TransferFiles $files): Response
    {
        $this->denyUnlessInContext($import);
        $path = match ($kind) {
            'original' => $import->getFilePath(),
            'valides' => $import->getSuccessFilePath(),
            default => $import->getErrorFilePath(),
        };
        $name = pathinfo((string) $import->getOriginalName(), \PATHINFO_FILENAME);

        return $this->download($files, $path, $import->getFormat(), 'original' === $kind ? (string) $import->getOriginalName() : \sprintf('%s-%s.%s', $name, $kind, $import->getFormat()?->value));
    }

    /**
     * The upload of a spreadsheet to check (app_event_import_new): on the import page, and on a report for the
     * corrected file of its errors. None for those who may not import.
     */
    private function getCreateForm(): ?FormInterface
    {
        return $this->isGranted(EventVoter::CREATE)
            ? $this->createForm(EventImportType::class, null, ['action' => $this->generateUrl('app_event_import_new')])
            : null;
    }

    private function denyUnlessValidToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
