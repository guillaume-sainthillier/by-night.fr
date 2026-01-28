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
use App\Api\Model\FeedbackInput;
use App\Api\Model\FeedbackOutput;
use App\Entity\User;
use App\Manager\MailerManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<FeedbackInput, FeedbackOutput>
 */
final readonly class FeedbackProcessor implements ProcessorInterface
{
    public function __construct(
        private MailerManager $mailerManager,
        private Security $security,
        #[Autowire('%feedback_recipient_email%')]
        private string $feedbackRecipientEmail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FeedbackOutput
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $this->mailerManager->sendFeedbackEmail($user, $data->message, $this->feedbackRecipientEmail);

        return new FeedbackOutput(
            success: true,
            message: 'Merci pour votre retour !',
        );
    }
}
