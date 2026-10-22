<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\App\Location;
use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ContentRemovalType;
use App\Search\DateRange;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

final readonly class MailerManager
{
    private const string SUPPORT_EMAIL = 'support@by-night.fr';

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        #[Autowire(env: 'SUPPORT_BCC_EMAIL')]
        private string $supportBccEmail,
    ) {
    }

    public function sendConfirmEmailEmail(User $user, array $context): void
    {
        $email = $this->createEmail('email/confirmation-email')
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse e-mail - By Night')
            ->context($context);

        $this->sendMail($email);
    }

    public function sendResetPasswordEmail(User $user, ResetPasswordToken $resetPasswordToken): void
    {
        $email = $this->createEmail('email/reset-password')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - By Night')
            ->context([
                'resetPasswordToken' => $resetPasswordToken,
            ]);

        $this->sendMail($email);
    }

    /**
     * The weekend selection of the member's city, which a mailbox offers to unsubscribe from in one click (RFC 8058).
     *
     * @param list<Event> $events
     */
    public function sendWeekendDigestEmail(User $user, Location $location, DateRange $weekend, array $events, string $unsubscribeUrl): void
    {
        $email = $this->createEmail('email/weekend-digest')
            ->to($user->getEmail())
            ->subject($this->translator->trans(
                "Ce week-end {at}\u{a0}: {count, plural, one {# sortie} other {# sorties}} à ne pas manquer",
                ['at' => $location->getAtName(), 'count' => \count($events)],
                'messages+intl-icu',
            ))
            ->context([
                'user' => $user,
                'location' => $location,
                'weekend' => $weekend,
                'events' => $events,
                'unsubscribeUrl' => $unsubscribeUrl,
            ]);

        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>')
            ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->sendMail($email);
    }

    public function sendFeedbackEmail(User $user, string $message): void
    {
        $email = $this->createSupportEmail('email/feedback', $user->getEmail())
            ->subject('Feedback utilisateur - By Night')
            ->context([
                'user' => $user,
                'message' => $message,
            ]);

        $this->sendMail($email);
    }

    public function sendContentRemovalRequestEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
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

        $email = $this->createSupportEmail('email/content-removal-request', $contentRemovalRequest->getEmail())
            ->subject('Demande de suppression de contenu - By Night')
            ->context($context);

        $this->sendMail($email);
    }

    public function sendContentRemovalEventDeletedEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
        $this->sendRequesterNotification(
            $contentRemovalRequest,
            'Votre demande de suppression a été traitée - By Night',
            'email/content-removal-event-deleted',
        );
    }

    public function sendContentRemovalProcessedEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
        $this->sendRequesterNotification(
            $contentRemovalRequest,
            'Votre demande de suppression a été traitée - By Night',
            'email/content-removal-processed',
        );
    }

    public function sendContentRemovalRejectedEmail(ContentRemovalRequest $contentRemovalRequest): void
    {
        $this->sendRequesterNotification(
            $contentRemovalRequest,
            'Votre demande de suppression a été examinée - By Night',
            'email/content-removal-rejected',
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

        $email = $this->createEmail($template)
            ->to($recipientEmail)
            ->subject($subject)
            ->context(['contentRemovalRequest' => $contentRemovalRequest]);

        $this->sendMail($email);
    }

    /**
     * @param string $template the template name without extension: the HTML part renders <template>.mjml.twig, the
     *                         text part <template>.txt.twig
     */
    private function createEmail(string $template): TemplatedEmail
    {
        return new TemplatedEmail()
            ->htmlTemplate($template . '.mjml.twig')
            ->textTemplate($template . '.txt.twig');
    }

    /**
     * An e-mail to the team: answering it writes back to the member or visitor who sent it.
     */
    private function createSupportEmail(string $template, ?string $replyTo): TemplatedEmail
    {
        $email = $this->createEmail($template)->to(self::SUPPORT_EMAIL);

        if ('' !== $this->supportBccEmail) {
            $email->bcc($this->supportBccEmail);
        }

        if (null !== $replyTo) {
            $email->replyTo($replyTo);
        }

        return $email;
    }

    private function generateUrl(string $route, array $params): string
    {
        return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function sendMail(Email $email): void
    {
        $email->from(new Address(self::SUPPORT_EMAIL, 'By Night'));
        $this->mailer->send($email);
    }
}
