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
use App\Repository\ContentRemovalRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ContentRemovalRequestInput, ContentRemovalRequestOutput>
 */
final readonly class ContentRemovalRequestProcessor implements ProcessorInterface
{
    private const string SENT = 'Votre demande a bien été envoyée. Nous la traiterons dans les plus brefs délais.';

    public function __construct(
        private MailerManager $mailerManager,
        private EntityManagerInterface $entityManager,
        private ContentRemovalRequestRepository $contentRemovalRequestRepository,
        #[Autowire(param: 'content_removal_recipient_email')]
        private string $contentRemovalRecipientEmail,
        #[Autowire(service: 'limiter.content_removal_request')]
        private RateLimiterFactoryInterface $limiterFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContentRemovalRequestOutput
    {
        $request = $context['request'];
        $event = $request->attributes->get('read_data');
        if (!$event instanceof Event) {
            throw new NotFoundHttpException('Not Found');
        }

        // Each request sends the moderators an e-mail: an anonymous form must not let one
        // address flood them
        $limit = $this->limiterFactory->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Vous avez envoyé trop de demandes, veuillez réessayer plus tard.');
        }

        // Sent twice (a double click, a second try): the pending request is the one to process
        $type = $data->type;
        \assert(null !== $type);
        if (null !== $this->contentRemovalRequestRepository->findPendingDuplicate($data->email, $type, $event)) {
            return new ContentRemovalRequestOutput(success: true, message: self::SENT);
        }

        // Only the event of the page the request was made from: the requester lists the other
        // pages in its URLs, for the moderators to check. Events chosen by id, as the API also
        // took, were deleted along with it by one click on "Supprimer les événements".
        $contentRemovalRequest = new ContentRemovalRequest();
        $contentRemovalRequest->setEmail($data->email);
        $contentRemovalRequest->setType($type);
        $contentRemovalRequest->setMessage($data->message);
        $contentRemovalRequest->setEventUrls($data->eventUrls ?: null);
        $contentRemovalRequest->addEvent($event);

        $this->entityManager->persist($contentRemovalRequest);
        $this->entityManager->flush();

        $this->mailerManager->sendContentRemovalRequestEmail(
            $contentRemovalRequest,
            $this->contentRemovalRecipientEmail,
        );

        return new ContentRemovalRequestOutput(success: true, message: self::SENT);
    }
}
