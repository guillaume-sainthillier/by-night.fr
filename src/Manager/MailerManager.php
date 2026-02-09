<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\ContentRemovalType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

final readonly class MailerManager
{
    public function __construct(private MailerInterface $mailer)
    {
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

    /**
     * @param string[] $eventUrls
     */
    public function sendContentRemovalRequestEmail(
        Event $event,
        string $requesterEmail,
        ContentRemovalType $type,
        string $message,
        array $eventUrls,
        string $recipientEmail,
    ): void {
        $email = new TemplatedEmail()
            ->to($recipientEmail)
            ->replyTo($requesterEmail)
            ->subject('Demande de suppression de contenu - By Night')
            ->htmlTemplate('email/content-removal-request.html.twig')
            ->context([
                'event' => $event,
                'requesterEmail' => $requesterEmail,
                'removalType' => $type,
                'message' => $message,
                'eventUrls' => $eventUrls,
            ]);

        $this->sendMail($email);
    }

    private function sendMail(Email $email): void
    {
        $email->from(new Address('support@by-night.fr', 'By Night'));
        $this->mailer->send($email);
    }
}
