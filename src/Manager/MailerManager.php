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
use App\Digest\WeekendDigestSender;
use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Entity\Publisher;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Enum\ContentRemovalType;
use App\Enum\PublisherNewsEdition;
use App\Outing\OutingChange;
use App\Outing\OutingChangeWording;
use App\Outing\OutingState;
use App\Search\DateRange;
use App\Utils\EmailUtm;
use DateTimeImmutable;
use LogicException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
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
        private UriSigner $uriSigner,
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
                'utm' => EmailUtm::campaign('weekend-digest', WeekendDigestSender::weekOf($weekend)),
            ]);

        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>')
            ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->sendMail($email);
    }

    /**
     * Tells a member going to an event what changed in it (OutingChangeNotifier). It can be turned off in one click,
     * from the e-mail or the mail app (RFC 8058).
     *
     * @param non-empty-list<OutingChange> $changes the most serious first
     * @param OutingState                  $known   what the member knew of the event
     */
    public function sendOutingChangedEmail(UserEvent $outing, array $changes, OutingState $known): void
    {
        $user = $outing->getUser() ?? throw new LogicException('The outing has no member.');
        $event = $outing->getEvent() ?? throw new LogicException('The outing has no event.');
        $now = OutingState::fromEvent($event);
        $unsubscribeUrl = $this->uriSigner->sign($this->generateUrl('app_outing_alerts_unsubscribe', ['id' => $user->getId()]));

        $email = $this->createEmail('email/outing-changed')
            ->to((string) $user->getEmail())
            ->subject($changes[0]->getSubject() . "\u{a0}: " . $event->getName())
            ->context([
                'event' => $event,
                'eventUrl' => $this->generateUrl('app_event_details', ['slug' => $event->getSlug(), 'id' => $event->getId(), 'location' => $event->getLocationSlug()]),
                'headline' => $changes[0]->getSubject(),
                'sentences' => array_map(static fn (OutingChange $change): string => OutingChangeWording::sentence($change, $known, $now), $changes),
                'unsubscribeUrl' => $unsubscribeUrl,
                'utm' => EmailUtm::campaign('outing-alert', $changes[0]->value),
            ]);

        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>')
            ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->sendMail($email);
    }

    /**
     * The link that turns off the second factors of a member who lost them (App\Manager\Security\TwoFactorRecoveryManager)
     */
    public function sendTwoFactorRecoveryLink(User $user, string $url, DateTimeImmutable $expiresAt): void
    {
        $email = $this->createEmail('email/two-factor-recovery')
            ->to($user->getEmail())
            ->subject('Récupérer l\'accès à votre compte - By Night')
            ->context(['user' => $user, 'url' => $url, 'expiresAt' => $expiresAt]);

        $this->sendMail($email);
    }

    public function sendTwoFactorReset(User $user): void
    {
        $email = $this->createEmail('email/two-factor-reset')
            ->to($user->getEmail())
            ->subject('La double authentification de votre compte est désactivée - By Night')
            ->context(['user' => $user]);

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

    /**
     * An edition of the publishers' news. It carries the one-click unsubscribe headers (RFC 8058) the large mailbox
     * providers ask of a bulk sender: their "Unsubscribe" button posts to $unsubscribeUrl.
     *
     * @param string|null $to another address than the member's, to preview the edition
     */
    public function sendPublisherNews(User $user, ?Publisher $publisher, PublisherNewsEdition $edition, string $unsubscribeUrl, ?string $to = null): void
    {
        $email = $this->createEmail($edition->getTemplate())
            ->to($to ?? $user->getEmail())
            ->subject($edition->getSubject())
            ->context([
                'user' => $user,
                'publisher' => $publisher,
                'unsubscribeUrl' => $unsubscribeUrl,
                'utm' => EmailUtm::campaign('publisher-news', $edition->value),
            ]);

        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>')
            ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->sendMail($email);
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
