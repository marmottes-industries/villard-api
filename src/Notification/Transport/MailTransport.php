<?php

namespace App\Notification\Transport;

use App\Notification\AppNotification;
use App\Notification\Channel;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends a notification as an HTML email rendered from a Twig template.
 * No-ops (with a log line) when the recipient has no email address.
 */
final readonly class MailTransport implements NotificationTransport
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%app.mailer.from%')]
        private string $fromAddress,
        #[Autowire('%app.mailer.from_name%')]
        private string $fromName,
    ) {
    }

    public function getChannel(): Channel
    {
        return Channel::Mail;
    }

    public function send(AppNotification $notification): void
    {
        $recipient = $notification->getRecipient();
        $to = $recipient->getEmail();

        if (!$to) {
            $this->logger->info('Skipping mail notification: recipient has no email', [
                'recipient' => $recipient->getUsername(),
            ]);

            return;
        }

        $email = new TemplatedEmail()
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject($notification->getSubject())
            ->htmlTemplate($notification->getMailTemplate())
            ->context($notification->getContext());

        $this->mailer->send($email);
    }
}
