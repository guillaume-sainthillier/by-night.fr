<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\ContentRemovalRequest;
use App\Entity\User;
use App\Enum\ContentRemovalType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

final readonly class MailerManager
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendConfirmEmailEmail(User $user, array $context): void
    {
        $email = new TemplatedEmail()
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse mail')
            ->htmlTemplate('email/confirmation-email.html.twig')
            ->context($context);

        $this->sendMail($email);
    }

    public function sendResetPasswordEmail(User $user, ResetPasswordToken $resetPasswordToken, int $tokenLifeTime): void
    {
        $email = new TemplatedEmail()
            ->to($user->getEmail())
            ->subject('Changement de mot de passe')
            ->htmlTemplate('email/reset-password.html.twig')
            ->context([
                'resetPasswordToken' => $resetPasswordToken,
                'tokenLifetime' => $tokenLifeTime,
            ]);

        $this->sendMail($email);
    }

    public function sendFeedbackEmail(User $user, string $message, string $recipientEmail): void
    {
        $email = new TemplatedEmail()
            ->to($recipientEmail)
            ->subject('Feedback utilisateur - By Night')
            ->htmlTemplate('email/feedback.html.twig')
            ->context([
                'user' => $user,
                'message' => $message,
            ]);

        $this->sendMail($email);
    }

    public function sendContentRemovalRequestEmail(
        ContentRemovalRequest $contentRemovalRequest,
        string $recipientEmail,
    ): void {
        $id = $contentRemovalRequest->getId();

        $context = [
            'contentRemovalRequest' => $contentRemovalRequest,
            'markProcessedUrl' => $this->generateUrl('admin_content_removal_action_mark_processed', ['id' => $id]),
            'rejectUrl' => $this->generateUrl('admin_content_removal_action_reject', ['id' => $id]),
        ];

        if (ContentRemovalType::Event === $contentRemovalRequest->getType()) {
            $context['removeEventsUrl'] = $this->generateUrl('admin_content_removal_action_remove_events', ['id' => $id]);
        }

        if (ContentRemovalType::Image === $contentRemovalRequest->getType()) {
            $context['removeImagesUrl'] = $this->generateUrl('admin_content_removal_action_remove_images', ['id' => $id]);
        }

        $email = new TemplatedEmail()
            ->to($recipientEmail)
            ->replyTo($contentRemovalRequest->getEmail())
            ->subject('Demande de suppression de contenu - By Night')
            ->htmlTemplate('email/content-removal-request.html.twig')
            ->context($context);

        $this->sendMail($email);
    }

    public function sendContentRemovalEventDeletedEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
        $this->sendRequesterNotification(
            $contentRemovalRequest,
            'Votre signalement a été traité - By Night',
            'email/content-removal-event-deleted.html.twig',
        );
    }

    public function sendContentRemovalProcessedEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
        $this->sendRequesterNotification(
            $contentRemovalRequest,
            'Votre demande de suppression a été traitée - By Night',
            'email/content-removal-processed.html.twig',
        );
    }

    public function sendContentRemovalRejectedEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
        $this->sendRequesterNotification(
            $contentRemovalRequest,
            'Votre demande de suppression a été examinée - By Night',
            'email/content-removal-rejected.html.twig',
        );
    }

    private function sendRequesterNotification(
        ContentRemovalRequest $contentRemovalRequest,
        string $subject,
        string $template,
    ): void {
        $recipientEmail = $contentRemovalRequest->getEmail();
        if (null === $recipientEmail) {
            return;
        }

        $email = new TemplatedEmail()
            ->to($recipientEmail)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(['contentRemovalRequest' => $contentRemovalRequest]);

        $this->sendMail($email);
    }

    private function generateUrl(string $route, array $params): string
    {
        return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function sendMail(Email $email): void
    {
        $email->from(new Address('support@by-night.fr', 'By Night'));
        $this->mailer->send($email);
    }
}
