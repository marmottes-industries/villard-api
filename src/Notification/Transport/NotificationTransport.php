<?php

namespace App\Notification\Transport;

use App\Notification\AppNotification;
use App\Notification\Channel;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Delivers notifications over a single {@see Channel}. Implementations are
 * auto-tagged and collected by {@see \App\Notification\Notifier}, so adding a new
 * channel only requires a new implementation of this interface.
 */
#[AutoconfigureTag('app.notification_transport')]
interface NotificationTransport
{
    public function getChannel(): Channel;

    public function send(AppNotification $notification): void;
}
