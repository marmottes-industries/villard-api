<?php

namespace App\Notification;

use App\Entity\User;

/**
 * A notification to deliver to a single user across one or more {@see Channel}s.
 *
 * To add a new notification type, implement this interface in a small value class
 * (see {@see OccupationEndingNotification}) and hand an instance to
 * {@see Notifier::send()}. No transport or dispatcher changes are needed.
 */
interface AppNotification
{
    /**
     * The user who should receive this notification.
     */
    public function getRecipient(): User;

    /**
     * Channels this notification should be delivered on.
     *
     * @return Channel[]
     */
    public function getChannels(): array;

    /**
     * Short headline: used as the email subject and the push notification title.
     */
    public function getSubject(): string;

    /**
     * Twig template (relative to templates/) rendered as the HTML email body.
     */
    public function getMailTemplate(): string;

    /**
     * Variables passed to the email template.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array;

    /**
     * Plain-text body of the push notification.
     */
    public function getPushBody(): string;

    /**
     * Arbitrary payload attached to the push notification (e.g. a deep-link route
     * the app reads when the notification is tapped).
     *
     * @return array<string, mixed>
     */
    public function getPushData(): array;
}
