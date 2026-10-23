<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace\Transfer;

use App\App\PublisherContext;
use App\Controller\AbstractController as BaseController;
use App\Entity\EventExport;
use App\Entity\EventImport;
use App\Entity\User;
use App\Enum\TransferFormat;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Transfer\TransferFiles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the import and export pages share: they are a publisher's (PublisherContext), their jobs run on a worker (the
 * "transfers" transport), one at a time per member, and their files are downloaded from the private transfers.storage.
 */
abstract class AbstractTransferController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        protected readonly PublisherContext $publisherContext,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * A job is reached on the pages of its publisher only: another one's URL does not lead to it.
     */
    protected function denyUnlessInContext(EventImport|EventExport $job): void
    {
        if ($job->getPublisher()?->getId() !== $this->publisherContext->get()->getId()) {
            throw $this->createNotFoundException('The job is not one of this publisher.');
        }
    }

    /**
     * One job at a time per member, imports and exports together: a second one waits for the first to finish.
     */
    protected function hasNoJobRunning(User $user, EventImportRepository $importRepository, EventExportRepository $exportRepository): bool
    {
        $running = $importRepository->countRunningByUser($user) + $exportRepository->countRunningByUser($user);
        if (0 < $running) {
            $this->addFlash('warning', 'Un import ou un export est déjà en cours : attendez qu’il se termine pour en lancer un autre.');
        }

        return 0 === $running;
    }

    protected function download(TransferFiles $files, ?string $path, ?TransferFormat $format, string $name): Response
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

    protected function formErrors(FormInterface $form): string
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }

        return implode(' ', $messages);
    }
}
