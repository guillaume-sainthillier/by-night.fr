<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\ContentRemovalRequestInput;
use App\Api\Model\ContentRemovalRequestOutput;
use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Manager\MailerManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ContentRemovalRequestInput, ContentRemovalRequestOutput>
 */
final readonly class ContentRemovalRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private MailerManager $mailerManager,
        private EntityManagerInterface $entityManager,
        #[Autowire('%content_removal_recipient_email%')]
        private string $contentRemovalRecipientEmail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContentRemovalRequestOutput
    {
        $event = $context['request']->attributes->get('read_data');
        if (!$event instanceof Event) {
            throw new NotFoundHttpException('Not Found');
        }

        $contentRemovalRequest = new ContentRemovalRequest();
        $contentRemovalRequest->setEmail($data->email);
        $contentRemovalRequest->setType($data->type);
        $contentRemovalRequest->setMessage($data->message);
        $contentRemovalRequest->setEventUrls($data->eventUrls ?: null);
        $contentRemovalRequest->setEvent($event);

        $this->entityManager->persist($contentRemovalRequest);
        $this->entityManager->flush();

        $this->mailerManager->sendContentRemovalRequestEmail(
            $event,
            $data->email,
            $data->type,
            $data->message,
            $data->eventUrls,
            $this->contentRemovalRecipientEmail
        );

        return new ContentRemovalRequestOutput(
            success: true,
            message: 'Votre demande a bien été envoyée. Nous la traiterons dans les plus brefs délais.',
        );
    }
}
